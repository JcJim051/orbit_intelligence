<?php

namespace App\Services\Ods;

use App\Models\IndicadorResultado;
use App\Models\IndicadorResultadoOdsComment;
use App\Models\IndicadorResultadoOdsLink;
use App\Models\IndicadorResultadoOdsReview;
use App\Models\OdsIndicator;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuggestOdsIndicatorRelations
{
    private const MIN_SCORE = 45;

    private const MAX_SUGGESTIONS_PER_INDICATOR = 3;

    /** @var array<string, array<int, string>> */
    private const GOAL_KEYWORDS = [
        '1' => ['pobreza', 'pobre', 'vulnerabilidad', 'ingreso', 'ipm'],
        '2' => ['hambre', 'aliment', 'nutricion', 'agricola', 'agropecuario', 'seguridad alimentaria'],
        '3' => ['salud', 'mortalidad', 'morbilidad', 'vacun', 'hospital', 'enfermedad', 'materna', 'nacidos'],
        '4' => ['educacion', 'colegio', 'matricula', 'estudiante', 'docente', 'aprendizaje', 'escolar'],
        '5' => ['mujer', 'genero', 'violencia contra la mujer', 'igualdad'],
        '6' => ['agua', 'saneamiento', 'alcantarillado', 'acueducto'],
        '7' => ['energia', 'electrica', 'renovable'],
        '8' => ['empleo', 'trabajo', 'desempleo', 'crecimiento economico', 'turismo'],
        '9' => ['infraestructura', 'innovacion', 'industria', 'internet', 'tecnologia', 'tic', 'conectividad'],
        '10' => ['desigualdad', 'inclusion', 'discapacidad', 'victimas', 'migrante'],
        '11' => ['vivienda', 'ciudad', 'territorio', 'movilidad', 'transporte', 'riesgo', 'desastre'],
        '12' => ['residuo', 'reciclaje', 'consumo', 'produccion sostenible'],
        '13' => ['clima', 'climatico', 'emisiones', 'adaptacion', 'mitigacion'],
        '14' => ['oceano', 'marino', 'pesca'],
        '15' => ['bosque', 'deforestacion', 'biodiversidad', 'ecosistema', 'ambiental', 'areas protegidas'],
        '16' => ['paz', 'justicia', 'seguridad', 'violencia', 'institucional', 'instituciones', 'informacion implementados', 'sistemas informacion'],
        '17' => ['alianza', 'cooperacion', 'datos', 'estadistica', 'monitoreo', 'seguimiento', 'informacion estadistica'],
    ];

    /** @var array<string, array<int, string>> */
    private const SYNONYMS = [
        'acueducto' => ['agua', 'servicio', 'saneamiento'],
        'alcantarillado' => ['agua', 'saneamiento', 'servicio'],
        'ambiental' => ['ambiente', 'ecosistema', 'biodiversidad'],
        'atencion' => ['servicio', 'cobertura', 'acceso'],
        'calidad' => ['mejoramiento', 'cumplimiento', 'desempeno'],
        'cobertura' => ['acceso', 'servicio', 'atencion'],
        'conectividad' => ['internet', 'tic', 'tecnologia'],
        'datos' => ['informacion', 'estadistica', 'monitoreo', 'seguimiento'],
        'educacion' => ['escolar', 'aprendizaje', 'estudiante'],
        'estadistica' => ['datos', 'informacion', 'monitoreo', 'seguimiento'],
        'informacion' => ['datos', 'estadistica', 'monitoreo', 'seguimiento', 'sistemas'],
        'infraestructura' => ['equipamiento', 'instalaciones', 'servicio'],
        'institucional' => ['instituciones', 'gestion', 'gobierno'],
        'mortalidad' => ['muertes', 'salud', 'vida'],
        'mujer' => ['genero', 'igualdad'],
        'salud' => ['sanitario', 'servicios', 'hospital', 'atencion'],
        'seguridad' => ['violencia', 'paz', 'convivencia'],
        'seguimiento' => ['monitoreo', 'datos', 'informacion'],
        'sistemas' => ['informacion', 'datos', 'instituciones', 'monitoreo'],
        'tecnologia' => ['innovacion', 'tic', 'conectividad'],
        'vivienda' => ['habitat', 'ciudad', 'territorio'],
    ];

    /** @var array<string, true> */
    private const STOP_WORDS = [
        'de' => true, 'del' => true, 'la' => true, 'las' => true, 'el' => true, 'los' => true,
        'en' => true, 'y' => true, 'o' => true, 'a' => true, 'por' => true, 'para' => true,
        'con' => true, 'sin' => true, 'que' => true, 'un' => true, 'una' => true, 'al' => true,
        'se' => true, 'su' => true, 'sus' => true, 'es' => true, 'son' => true, 'numero' => true,
        'porcentaje' => true, 'tasa' => true, 'indice' => true, 'total' => true, 'personas' => true,
        'poblacion' => true, 'realizados' => true, 'realizadas' => true, 'documento' => true,
        'documentos' => true, 'lineamiento' => true, 'lineamientos' => true, 'tecnico' => true,
        'tecnicos' => true, 'entidades' => true, 'organismos' => true, 'dependencias' => true,
        'asistidos' => true, 'asistidas' => true, 'oferta' => true, 'parametros' => true,
        'calidad' => true,
    ];

    /**
     * @return array{reviewed:int, created:int, skipped_existing:int}
     */
    public function generate(?User $user = null): array
    {
        $reviews = IndicadorResultadoOdsReview::query()
            ->with(['indicador.metasResultado', 'links'])
            ->get();

        if ($reviews->isEmpty()) {
            $this->ensureReviewTasks();
            $reviews = IndicadorResultadoOdsReview::query()
                ->with(['indicador.metasResultado', 'links'])
                ->get();
        }

        $odsIndicators = OdsIndicator::query()
            ->with(['target.goal'])
            ->where('active', true)
            ->get();

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($reviews, $odsIndicators, $user, &$created, &$skipped): void {
            foreach ($reviews as $review) {
                if ($review->links->contains(fn (IndicadorResultadoOdsLink $link): bool => in_array($link->status, ['accepted', 'rejected'], true))) {
                    $skipped++;

                    continue;
                }

                $suggestions = $this->suggestionsFor($review->indicador, $odsIndicators);

                foreach ($suggestions as $suggestion) {
                    $link = IndicadorResultadoOdsLink::query()->firstOrCreate(
                        [
                            'indicador_resultado_id' => $review->indicador_resultado_id,
                            'ods_indicator_id' => $suggestion['indicator']->id,
                        ],
                        [
                            'review_id' => $review->id,
                            'relation_type' => $suggestion['relation_type'],
                            'confidence' => $suggestion['confidence'],
                            'status' => 'proposed',
                            'justification' => $suggestion['justification'],
                            'created_by' => $user?->id,
                        ],
                    );

                    if (! $link->wasRecentlyCreated) {
                        $skipped++;

                        continue;
                    }

                    $created++;
                    IndicadorResultadoOdsComment::query()->create([
                        'review_id' => $review->id,
                        'link_id' => $link->id,
                        'user_id' => $user?->id,
                        'event_type' => 'auto_suggestion',
                        'comment' => 'Sugerencia automática creada para revisión humana. No queda aceptada hasta que un revisor la valide.',
                        'metadata' => [
                            'score' => $suggestion['score'],
                            'shared_terms' => $suggestion['shared_terms'],
                            'matched_goal' => $suggestion['matched_goal'],
                            'reasons' => $suggestion['reasons'],
                        ],
                    ]);
                }

                if ($review->status === 'pending' && $suggestions !== []) {
                    $review->update(['status' => 'in_review']);
                }
            }
        });

        return [
            'reviewed' => $reviews->count(),
            'created' => $created,
            'skipped_existing' => $skipped,
        ];
    }

    /**
     * @param  Collection<int, OdsIndicator>  $odsIndicators
     * @return list<array{indicator: OdsIndicator, score: int, relation_type: string, confidence: string, justification: string, shared_terms: list<string>, matched_goal: bool, reasons: list<string>}>
     */
    public function suggestionsFor(IndicadorResultado $indicator, Collection $odsIndicators): array
    {
        $sourceText = $this->indicatorText($indicator);
        $sourceTokens = $this->tokens($sourceText);
        $nameTokens = $this->tokens($this->normalize($indicator->nombre));

        if ($nameTokens === []) {
            return [];
        }

        $inferredGoals = $this->inferGoals($sourceText);
        $suggestions = [];

        foreach ($odsIndicators as $odsIndicator) {
            $targetText = $this->normalize($odsIndicator->code.' '.$odsIndicator->name.' '.$odsIndicator->description.' '.$odsIndicator->target?->name.' '.$odsIndicator->target?->goal?->name);
            $targetTokens = $this->tokens($targetText);
            $shared = array_values(array_intersect($sourceTokens, $targetTokens));
            $score = $this->score($sourceText, $targetText, $sourceTokens, $targetTokens, $inferredGoals, (string) $odsIndicator->target?->goal?->code);
            $reasons = $this->reasons($score, $shared, $inferredGoals, (string) $odsIndicator->target?->goal?->code);

            $matchedGoal = in_array((string) $odsIndicator->target?->goal?->code, $inferredGoals, true);

            if ($score < self::MIN_SCORE || (! $matchedGoal && $score < 50)) {
                continue;
            }

            $suggestions[] = [
                'indicator' => $odsIndicator,
                'score' => $score,
                'relation_type' => $score >= 72 ? 'direct' : ($score >= 55 ? 'partial' : 'contextual'),
                'confidence' => $score >= 72 ? 'high' : ($score >= 55 ? 'medium' : 'low'),
                'justification' => $this->justification($indicator, $odsIndicator, $score, $shared, $reasons),
                'shared_terms' => array_slice($shared, 0, 12),
                'matched_goal' => $matchedGoal,
                'reasons' => $reasons,
            ];
        }

        usort($suggestions, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, self::MAX_SUGGESTIONS_PER_INDICATOR);
    }

    private function ensureReviewTasks(): void
    {
        IndicadorResultado::query()
            ->select('id')
            ->whereDoesntHave('odsReview')
            ->lazyById()
            ->each(fn (IndicadorResultado $indicador) => IndicadorResultadoOdsReview::query()->firstOrCreate([
                'indicador_resultado_id' => $indicador->id,
            ]));
    }

    private function indicatorText(IndicadorResultado $indicator): string
    {
        return $this->normalize(implode(' ', array_filter([
            $indicator->codigo,
            $indicator->nombre,
            $indicator->unidad_medida,
            $indicator->fuente_verificacion,
            $indicator->metasResultado->pluck('descripcion')->implode(' '),
        ])));
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];

        $tokens = array_values(array_unique(array_filter($words, fn (string $word): bool => mb_strlen($word) >= 3 && ! isset(self::STOP_WORDS[$word]))));

        foreach ($tokens as $token) {
            foreach (self::SYNONYMS[$token] ?? [] as $synonym) {
                if (! isset(self::STOP_WORDS[$synonym])) {
                    $tokens[] = $synonym;
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return list<string>
     */
    private function inferGoals(string $text): array
    {
        $goals = [];

        foreach (self::GOAL_KEYWORDS as $goal => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $this->normalize($keyword))) {
                    $goals[] = (string) $goal;
                    break;
                }
            }
        }

        return array_values(array_unique($goals));
    }

    /**
     * @param  list<string>  $sourceTokens
     * @param  list<string>  $targetTokens
     * @param  list<string>  $inferredGoals
     */
    private function score(string $sourceText, string $targetText, array $sourceTokens, array $targetTokens, array $inferredGoals, string $targetGoal): int
    {
        if ($sourceTokens === [] || $targetTokens === []) {
            return 0;
        }

        $shared = array_values(array_intersect($sourceTokens, $targetTokens));
        $jaccard = count($shared) / max(1, count(array_unique(array_merge($sourceTokens, $targetTokens))));
        $coverage = count($shared) / max(1, count($sourceTokens));
        $targetCoverage = count($shared) / max(1, min(18, count($targetTokens)));
        $score = (int) round(($jaccard * 35) + ($coverage * 38) + ($targetCoverage * 16));

        if ($sourceText !== '' && str_contains($targetText, $sourceText)) {
            $score += 20;
        }

        if ($targetGoal !== '' && in_array($targetGoal, $inferredGoals, true)) {
            $score += 25;
        }

        if (count($shared) >= 4) {
            $score += 10;
        } elseif (count($shared) >= 2) {
            $score += 6;
        }

        if ($targetGoal !== '' && in_array($targetGoal, $inferredGoals, true) && count($shared) >= 2) {
            $score = max($score, 45);
        }

        return min(100, $score);
    }

    /**
     * @param  list<string>  $shared
     * @param  list<string>  $inferredGoals
     * @return list<string>
     */
    private function reasons(int $score, array $shared, array $inferredGoals, string $targetGoal): array
    {
        $reasons = ["Puntaje automático {$score}/100."];

        if ($shared !== []) {
            $reasons[] = 'Coinciden términos clave: '.implode(', ', array_slice($shared, 0, 8)).'.';
        }

        if ($targetGoal !== '' && in_array($targetGoal, $inferredGoals, true)) {
            $reasons[] = "El texto del indicador PDD sugiere el ODS {$targetGoal}.";
        }

        $reasons[] = 'Debe validarse manualmente antes de aceptarse.';

        return $reasons;
    }

    /**
     * @param  list<string>  $shared
     * @param  list<string>  $reasons
     */
    private function justification(IndicadorResultado $indicator, OdsIndicator $odsIndicator, int $score, array $shared, array $reasons): string
    {
        $terms = $shared === [] ? 'sin términos exactos fuertes' : implode(', ', array_slice($shared, 0, 8));

        return "Sugerencia automática para revisar la posible relación entre el indicador PDD \"{$indicator->nombre}\" y el indicador ODS {$odsIndicator->code} \"{$odsIndicator->name}\". Puntaje: {$score}/100. Términos compartidos: {$terms}. ".implode(' ', $reasons);
    }

    private function normalize(?string $value): string
    {
        $value = Str::lower($value ?? '');
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9 ]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
