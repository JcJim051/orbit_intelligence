<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Models\IndicadorResultadoOdsLink;
use App\Models\PddPilar;
use App\Models\Seguimiento;
use App\Services\Intelligence\Catalogs\PlanLabel;
use App\Services\Intelligence\OdsRelationsWorkbook;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OdsRelationsController extends Controller
{
    /** @var array<string, string> */
    public const STATUSES = [
        'proposed' => 'Propuesta',
        'accepted' => 'Confirmada',
        'rejected' => 'Rechazada',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()?->canViewOdsRelations(), 403);

        $relations = $this->baseQuery()->get();
        $pillars = PddPilar::query()->orderBy('codigo')->get();
        $pillarRows = $pillars->map(function (PddPilar $pillar) use ($relations): array {
            $details = $relations->map(function (IndicadorResultadoOdsLink $relation) use ($pillar): ?array {
                $metas = $relation->indicador?->metasResultado?->filter(function ($meta) use ($pillar): bool {
                    $metaPilar = $meta->programa?->linea?->eje?->pilar
                        ?? $meta->subprograma?->programa?->linea?->eje?->pilar;

                    return $metaPilar?->getKey() === $pillar->getKey();
                })->values() ?? collect();

                return $metas->isEmpty() ? null : ['relation' => $relation, 'metas' => $metas];
            })->filter()->values();

            return [
                'pillar' => $pillar,
                'details' => $details,
                'pillar_label' => PlanLabel::reference($pillar),
                'objective_count' => $details->pluck('relation.odsIndicator.target.ods_goal_id')->filter()->unique()->count(),
                'ods_count' => $details->pluck('relation.ods_indicator_id')->unique()->count(),
                'meta_count' => $details->flatMap(fn (array $detail) => $detail['metas']->pluck('id'))->unique()->count(),
                'result_indicator_count' => $details->pluck('relation.indicador_resultado_id')->unique()->count(),
            ];
        });

        return view('intelligence.ods-relations.index', [
            'pillarRows' => $pillarRows,
        ]);
    }

    public function show(Request $request, IndicadorResultadoOdsLink $relation): View
    {
        abort_unless($request->user()?->canViewOdsRelations(), 403);

        $relation->load([
            'odsIndicator.target.goal',
            'indicador.metasResultado.programa.linea.eje.pilar',
            'indicador.metasResultado.subprograma.programa.linea.eje.pilar',
        ]);

        return view('intelligence.ods-relations.show', [
            'relation' => $relation,
            'statusLabel' => self::STATUSES[$relation->status] ?? ucfirst((string) $relation->status),
        ]);
    }

    public function byDependency(Request $request): View
    {
        abort_unless($request->user()?->canViewOdsRelations(), 403);

        $relations = $this->baseQuery()->get();
        $dependencyRows = $this->dependencyRows($relations);
        $followUps = Seguimiento::query()->orderByDesc('vigencia')->orderByDesc('mes')->get();
        $followUp = $request->integer('seguimiento')
            ? $followUps->firstWhere('id', $request->integer('seguimiento'))
            : $followUps->first();

        return view('intelligence.ods-relations.by-dependency', [
            'dependencyRows' => $dependencyRows,
            'dependenciesWithRelations' => $dependencyRows->where('ods_count', '>', 0)->count(),
            'goalCount' => $relations->pluck('odsIndicator.target.ods_goal_id')->filter()->unique()->count(),
            'odsCount' => $relations->pluck('ods_indicator_id')->filter()->unique()->count(),
            'followUps' => $followUps,
            'followUp' => $followUp,
        ]);
    }

    public function downloadByDependency(Request $request, OdsRelationsWorkbook $workbook): BinaryFileResponse
    {
        abort_unless($request->user()?->canViewOdsRelations(), 403);

        $followUp = Seguimiento::query()->findOrFail($request->integer('seguimiento'));
        $rows = $this->dependencyRows($this->baseQuery()->where('status', 'accepted')->get());
        $path = storage_path('app/informe-ods-dependencias-'.uniqid().'.xlsx');
        $workbook->write($path, $rows, $followUp);

        return response()
            ->download($path, sprintf('informe-ods-dependencias-%d-%02d.xlsx', $followUp->vigencia, $followUp->mes))
            ->deleteFileAfterSend(true);
    }

    private function dependencyRows($relations)
    {
        return Dependencia::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(function (Dependencia $dependency) use ($relations): array {
                $details = $relations->map(function (IndicadorResultadoOdsLink $relation) use ($dependency): ?array {
                    $metas = $relation->indicador?->metasResultado?->map(function ($meta) use ($dependency): ?array {
                        $productos = $meta->metasProducto
                            ->where('dependencia_id', $dependency->id)
                            ->values();

                        return $productos->isEmpty() ? null : [
                            'meta' => $meta,
                            'productos' => $productos,
                        ];
                    })->filter()->values() ?? collect();

                    return $metas->isEmpty() ? null : [
                        'relation' => $relation,
                        'metas' => $metas,
                    ];
                })->filter()->values();

                $goals = $details->pluck('relation.odsIndicator.target.goal')->filter()->unique('id')->sortBy('code')->values();
                $odsIndicators = $details->pluck('relation.odsIndicator')->filter()->unique('id')->sortBy('code')->values();
                $resultIndicators = $details->pluck('relation.indicador')->filter()->unique('id')->sortBy('codigo')->values();
                $resultGoals = $details->flatMap(fn (array $detail) => $detail['metas']->pluck('meta'))->filter()->unique('id')->sortBy('codigo_provisional')->values();
                $productGoals = $details->flatMap(fn (array $detail) => $detail['metas']->flatMap(fn (array $meta) => $meta['productos']))->filter()->unique('id')->sortBy('codigo')->values();

                return [
                    'dependency' => $dependency,
                    'details' => $details,
                    'goal_count' => $goals->count(),
                    'ods_count' => $odsIndicators->count(),
                    'meta_count' => $resultGoals->count(),
                    'product_count' => $productGoals->count(),
                    'result_indicator_count' => $resultIndicators->count(),
                    'counter_details' => [
                        'goals' => $goals->map(fn ($goal): array => [
                            'code' => $goal->code,
                            'name' => $goal->name,
                            'context' => 'Objetivo de Desarrollo Sostenible',
                        ]),
                        'ods_indicators' => $odsIndicators->map(fn ($indicator): array => [
                            'code' => $indicator->code,
                            'name' => $indicator->name,
                            'context' => 'Meta ODS '.($indicator->target?->code ?? 'sin código'),
                        ]),
                        'result_goals' => $resultGoals->map(fn ($goal): array => [
                            'code' => $goal->codigo_provisional ?? $goal->codigo ?? 'Sin código',
                            'name' => $goal->descripcion,
                            'context' => 'Meta de resultado',
                        ]),
                        'product_goals' => $productGoals->map(fn ($goal): array => [
                            'code' => $goal->codigo,
                            'name' => $goal->nombre,
                            'context' => 'Meta producto',
                        ]),
                        'result_indicators' => $resultIndicators->map(fn ($indicator): array => [
                            'code' => $indicator->codigo ?? 'Sin código',
                            'name' => $indicator->nombre,
                            'context' => 'Indicador de resultado',
                        ]),
                    ],
                ];
            });
    }

    private function baseQuery()
    {
        return IndicadorResultadoOdsLink::query()
            ->with([
                'odsIndicator.target.goal',
                'indicador.metasResultado.programa.linea.eje.pilar',
                'indicador.metasResultado.subprograma.programa.linea.eje.pilar',
                'indicador.metasResultado.metasProducto.dependencia',
            ])
            ->orderByDesc('updated_at')
            ->orderBy('id');
    }
}
