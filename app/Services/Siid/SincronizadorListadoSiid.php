<?php

namespace App\Services\Siid;

use App\Models\OdsIndicator;
use App\Models\SiidAreaTematica;
use App\Models\SiidEje;
use App\Models\SiidEquivalenciaArea;
use App\Models\SiidIndicador;
use App\Models\SiidSincronizacion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SincronizadorListadoSiid
{
    public function __construct(
        private readonly GoogleSheetsServiceAccountClient $client,
        private readonly ListadoMaestroParser $parser,
        private readonly ComplementaryMetadataParser $complementaryParser,
    ) {}

    public function execute(bool $apply, string $origin = 'manual', ?int $userId = null): SiidSincronizacion
    {
        return $this->fromSnapshot($this->client->snapshot(), $apply, $origin, $userId);
    }

    /** @param array{complete?: bool, sheets?: array<string, array<int, array<int, mixed>>>, complementary?: array<string, array<int, array<int, mixed>>>, warnings?: array<int, string>} $snapshot */
    public function fromSnapshot(array $snapshot, bool $apply, string $origin = 'manual', ?int $userId = null): SiidSincronizacion
    {
        $sync = SiidSincronizacion::create([
            'origen' => $origin, 'estado' => 'procesando', 'dry_run' => ! $apply,
            'ejecutado_por' => $userId, 'iniciado_at' => now(),
        ]);

        try {
            if (! ($snapshot['complete'] ?? false)) {
                throw new RuntimeException('La lectura de las pestañas principales está incompleta; no se aplicó ningún cambio.');
            }

            $records = [];
            foreach ($snapshot['sheets'] ?? [] as $sheet => $rows) {
                $parsed = $this->parser->parse($sheet, $rows);
                foreach ($parsed['errors'] as $error) {
                    $this->detail($sync, 'conflicto_estructura', null, $sheet, null, [], $error);
                }
                array_push($records, ...$parsed['rows']);
            }
            $records = $this->complementaryParser->enrich($records, $snapshot['complementary'] ?? []);

            if ($sync->detalles()->where('tipo', 'conflicto_estructura')->exists()) {
                throw new RuntimeException('Una pestaña principal no tiene una estructura válida.');
            }

            $normalized = $this->validateRecords($sync, $records);
            $seenCodes = collect($normalized)->pluck('codigo')->filter()->unique()->values();

            if ($apply) {
                DB::transaction(function () use ($sync, $normalized, $seenCodes): void {
                    foreach ($normalized as $record) {
                        if ($record['_blocked']) {
                            continue;
                        }
                        $this->upsert($sync, $record);
                    }

                    $inactive = SiidIndicador::query()->where('activo', true)->whereNotIn('codigo', $seenCodes)->get();
                    foreach ($inactive as $indicator) {
                        $indicator->update(['activo' => false, 'sincronizado_at' => now()]);
                        $indicator->publicacion()->update(['visible' => false]);
                        $sync->increment('inactivados');
                        $this->detail($sync, 'inactivado', $indicator->codigo, null, null, [], 'Ausente después de una lectura completa del listado maestro.');
                    }
                });
            } else {
                $this->countDryRun($sync, $normalized, $seenCodes);
            }

            foreach ($snapshot['warnings'] ?? [] as $warning) {
                $this->detail($sync, 'advertencia', null, null, null, [], $warning);
            }

            $warningCount = $sync->detalles()->where('tipo', 'advertencia')->count();
            $conflictCount = $sync->detalles()->where('tipo', 'like', 'conflicto%')->count();
            $sync->update([
                'estado' => $conflictCount > 0 ? 'completado_con_novedades' : 'completado',
                'leidos' => count($records), 'finalizado_at' => now(),
                'advertencias' => $warningCount,
                'conflictos' => $conflictCount,
            ]);
        } catch (Throwable $exception) {
            $sync->update(['estado' => 'fallido', 'error_general' => $exception->getMessage(), 'finalizado_at' => now()]);
        }

        return $sync->fresh(['detalles']);
    }

    /** @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function validateRecords(SiidSincronizacion $sync, array $records): array
    {
        $codeCounts = collect($records)->map(fn (array $record): string => Str::upper(preg_replace('/\s+/', '', (string) ($record['codigo'] ?? ''))))->countBy();
        $result = [];
        foreach ($records as $record) {
            $record['codigo'] = Str::upper(preg_replace('/\s+/', '', (string) ($record['codigo'] ?? '')));
            $record['_blocked'] = false;
            $code = $record['codigo'];
            if ($code === '') {
                $record['_blocked'] = true;
                $this->detail($sync, 'conflicto_codigo', null, $record['_sheet'], $record['_row'], $record, 'El código está vacío.');
            } elseif (($codeCounts[$code] ?? 0) > 1) {
                $record['_blocked'] = true;
                $this->detail($sync, 'conflicto_codigo', $code, $record['_sheet'], $record['_row'], $record, 'El código está repetido en el origen.');
            }

            $record['eje_id'] = $this->ejeId((string) $record['_sheet']);
            $record['area_tematica_id'] = $this->areaId((string) ($record['area'] ?? ''), $record['eje_id']);
            if (! $record['eje_id']) {
                $record['_blocked'] = true;
                $this->detail($sync, 'conflicto_eje', $code, $record['_sheet'], $record['_row'], $record, 'La pestaña no corresponde a uno de los tres ejes configurados.');
            }
            if (! $record['area_tematica_id']) {
                $record['_blocked'] = true;
                $this->detail($sync, 'conflicto_area', $code, $record['_sheet'], $record['_row'], $record, 'El área temática no tiene equivalencia canónica activa.');
            }

            $invalidUrl = collect(['url_ficha', 'url_datos', 'url_fuente'])->first(fn (string $field): bool => filled($record[$field] ?? null) && filter_var($record[$field], FILTER_VALIDATE_URL) === false);
            if ($invalidUrl) {
                $record['_blocked'] = true;
                $this->detail($sync, 'conflicto_url', $code, $record['_sheet'], $record['_row'], $record, "La URL de {$invalidUrl} no es válida.");
            }

            $dashboardReference = trim((string) ($record['url_tablero'] ?? ''));
            if ($dashboardReference !== '' && filter_var($dashboardReference, FILTER_VALIDATE_URL) === false && ! Str::isUuid($dashboardReference)) {
                $record['_blocked'] = true;
                $this->detail($sync, 'conflicto_url', $code, $record['_sheet'], $record['_row'], $record, 'El tablero debe contener una URL válida o un UUID de Superset.');
            }

            if ($existing = SiidIndicador::query()->where('codigo', $code)->first()) {
                similar_text(Str::lower($existing->nombre), Str::lower((string) ($record['nombre'] ?? '')), $similarity);
                if ($similarity < (int) config('siid.sheets.name_similarity_threshold', 65)) {
                    $record['_blocked'] = true;
                    $this->detail($sync, 'conflicto_nombre', $code, $record['_sheet'], $record['_row'], $record, "El nombre cambió de forma sustancial ({$similarity} % de similitud).");
                }
            }

            $result[] = $record;
        }

        return $result;
    }

    /** @param array<string, mixed> $record */
    private function upsert(SiidSincronizacion $sync, array $record): void
    {
        $indicator = SiidIndicador::query()->firstOrNew(['codigo' => $record['codigo']]);
        $isNew = ! $indicator->exists;
        $attributes = $this->attributes($record, $indicator);
        $indicator->fill($attributes);
        $changed = $indicator->isDirty();
        $indicator->save();
        $indicator->publicacion()->firstOrCreate([], ['visible' => false]);
        $this->syncOds($sync, $indicator, $record);
        $sync->increment($isNew ? 'creados' : ($changed ? 'actualizados' : 'sin_cambios'));
    }

    /** @param array<string, mixed> $record */
    private function attributes(array $record, SiidIndicador $indicator): array
    {
        $mapping = Arr::only($record, ['nombre', 'descripcion', 'fuente', 'url_fuente', 'unidad_medida', 'periodicidad', 'url_ficha', 'url_datos', 'url_tablero', 'tipo_tablero', 'procedencia', 'estado_listado', 'anio_actualizacion', 'fuentes_complementarias']);
        if (blank($mapping['tipo_tablero'] ?? null) && filled($mapping['url_tablero'] ?? null)) {
            $mapping['tipo_tablero'] = Str::isUuid((string) $mapping['url_tablero']) ? 'superset' : 'looker';
        }
        $attributes = [
            'eje_id' => $record['eje_id'], 'area_tematica_id' => $record['area_tematica_id'],
            'pestana_origen' => $record['_sheet'], 'activo' => true, 'sincronizado_at' => now(),
            'datos_origen' => Arr::except($record, ['_blocked']),
        ];
        foreach ($mapping as $key => $value) {
            if (filled($value) || ! $indicator->exists) {
                $attributes[$key] = $value;
            }
        }
        $attributes['es_icet'] = $this->truthy($record['icet'] ?? false);
        $attributes['es_pet'] = $this->truthy($record['pet'] ?? false);

        return $attributes;
    }

    /** @param array<string, mixed> $record */
    private function syncOds(SiidSincronizacion $sync, SiidIndicador $indicator, array $record): void
    {
        $relations = $record['ods_relaciones'] ?? [[
            'codigo' => $record['ods_codigo'] ?? null,
            'nombre' => $record['ods_nombre'] ?? null,
            'meta' => null,
        ]];
        foreach ($relations as $relation) {
            $code = trim((string) ($relation['codigo'] ?? ''));
            if ($code === '') {
                continue;
            }
            $matches = OdsIndicator::query()->where('code', $code)->get();
            if ($matches->count() > 1) {
                $this->detail($sync, 'conflicto_ods', $indicator->codigo, $record['_sheet'], $record['_row'], $relation, 'La relación ODS es ambigua.');
            }
            DB::table('siid_publicacion.indicador_ods')->updateOrInsert(
                ['indicador_siid_id' => $indicator->id, 'codigo_origen' => $code],
                ['ods_indicator_id' => $matches->count() === 1 ? $matches->first()->id : null, 'nombre_origen' => $relation['nombre'] ?? null, 'meta_origen' => $relation['meta'] ?? null, 'datos_origen' => json_encode($relation), 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    /** @param array<int, array<string, mixed>> $records */
    private function countDryRun(SiidSincronizacion $sync, array $records, $seenCodes): void
    {
        foreach ($records as $record) {
            if ($record['_blocked']) {
                continue;
            }
            $existing = SiidIndicador::query()->where('codigo', $record['codigo'])->first();
            if (! $existing) {
                $sync->increment('creados');
            } else {
                $probe = $existing->replicate()->fill($this->attributes($record, $existing));
                $sync->increment($probe->isDirty() ? 'actualizados' : 'sin_cambios');
            }
        }
        $sync->update(['inactivados' => SiidIndicador::query()->where('activo', true)->whereNotIn('codigo', $seenCodes)->count()]);
    }

    private function areaId(string $value, ?int $ejeId): ?int
    {
        $normalized = $this->parser->normalize($value);
        if ($ejeId) {
            $direct = SiidAreaTematica::query()
                ->where('eje_id', $ejeId)
                ->whereRaw('lower(nombre) = ?', [mb_strtolower(trim($value))])
                ->where('activo', true)
                ->value('id');
            if ($direct) {
                return $direct;
            }
        }

        $equivalence = SiidEquivalenciaArea::query()->where('texto_normalizado', $normalized)->where('activo', true)->first();
        if ($equivalence) {
            return $equivalence->area_tematica_id;
        }

        return null;
    }

    private function ejeId(string $sheet): ?int
    {
        $normalized = $this->parser->normalize($sheet);
        $code = match (true) {
            str_contains($normalized, 'sociodemografico') => 'SOCIODEMOGRAFICO',
            str_contains($normalized, 'economico') => 'ECONOMICO',
            str_contains($normalized, 'ambiental') => 'AMBIENTAL_TERRITORIAL',
            default => null,
        };

        return $code ? SiidEje::query()->where('codigo', $code)->value('id') : null;
    }

    /** @param array<string, mixed> $data */
    private function detail(SiidSincronizacion $sync, string $type, ?string $code, ?string $sheet, ?int $row, array $data, string $reason): void
    {
        $sync->detalles()->create(['tipo' => $type, 'pestana' => $sheet, 'fila' => $row, 'codigo' => $code, 'datos_recibidos' => $data, 'motivo' => $reason]);
    }

    private function truthy(mixed $value): bool
    {
        return in_array(Str::lower(trim((string) $value)), ['1', 'si', 'sí', 'true', 'x', 'aplica'], true);
    }
}
