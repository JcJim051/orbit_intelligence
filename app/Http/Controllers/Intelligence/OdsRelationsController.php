<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\IndicadorResultadoOdsLink;
use App\Models\PddPilar;
use App\Services\Intelligence\Catalogs\PlanLabel;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

    private function baseQuery()
    {
        return IndicadorResultadoOdsLink::query()
            ->with([
                'odsIndicator.target.goal',
                'indicador.metasResultado.programa.linea.eje.pilar',
                'indicador.metasResultado.subprograma.programa.linea.eje.pilar',
            ])
            ->orderByDesc('updated_at')
            ->orderBy('id');
    }
}
