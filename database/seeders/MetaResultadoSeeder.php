<?php

namespace Database\Seeders;

use App\Models\IndicadorResultado;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MetaResultadoSeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private array $notes = [];

    public function run(): void
    {
        $rows = $this->rows();
        $indicators = $this->indicators($rows);
        $indicatorIds = [];

        foreach ($indicators as $nombre => $attributes) {
            $indicator = IndicadorResultado::query()->firstOrNew(['nombre' => $nombre]);
            $indicator->fill($attributes);

            if (! $indicator->exists) {
                $indicator->codigo = null;
            }

            $indicator->save();
            $indicatorIds[$nombre] = $indicator->id;
        }

        $metas = [];

        foreach ($rows as $index => $row) {
            $provisional = sprintf('MR-%03d', (int) ($row['id_matriz'] !== '' ? $row['id_matriz'] : $index + 1));
            $nombre = $this->clean($row['indicador_resultado_nombre']);
            $indicatorId = $nombre === null ? null : ($indicatorIds[$nombre] ?? null);

            if ($indicatorId === null) {
                $this->notes[] = ['sin_indicador', $provisional, '', 'La matriz no trae nombre de indicador. No se inventó uno ni se copió la descripción.'];
            }

            $meta = MetaResultado::query()->firstOrNew(['codigo_provisional' => $provisional]);
            $meta->fill([
                'descripcion' => $this->clean($row['descripcion']) ?? '',
                'indicador_resultado_id' => $indicatorId,
                'linea_base' => $this->decimal($row['linea_base'], $provisional, 'linea_base'),
                'meta_cuatrienio' => $this->decimal($row['meta_cuatrienio'], $provisional, 'meta_cuatrienio'),
                'observacion' => $this->clean($row['nota_migracion']),
                'activo' => true,
            ]);

            if (! $meta->exists) {
                $meta->codigo = null;
            }

            $meta->save();
            $metas[] = ['meta' => $meta, 'row' => $row];
        }

        $ids = array_map(fn (array $entry): int => (int) $entry['meta']->id, $metas);
        MetaProducto::query()->whereIn('meta_resultado_id', $ids)->update(['meta_resultado_id' => null]);

        $claimed = [];
        $linked = 0;
        $withoutProducts = 0;
        $withoutPrograma = 0;

        foreach ($metas as $entry) {
            /** @var MetaResultado $meta */
            $meta = $entry['meta'];
            $codes = $this->productCodes($entry['row']['metas_producto_codigos']);
            $matchedIds = [];

            foreach ($codes as $code) {
                $product = MetaProducto::query()->where('codigo', $code)->first();

                if ($product === null) {
                    $this->notes[] = ['codigo_sin_produccion', $meta->codigo_provisional, $code, 'El código de la matriz no está en las 482 metas de producción. No se reasignó.'];

                    continue;
                }

                if (isset($claimed[$code])) {
                    $this->notes[] = ['codigo_ya_asignado', $meta->codigo_provisional, $code, 'El código ya quedó en '.$claimed[$code].'.'];

                    continue;
                }

                $claimed[$code] = $meta->codigo_provisional;
                $matchedIds[] = $product->id;
                $linked++;
            }

            if ($matchedIds !== []) {
                MetaProducto::query()->whereIn('id', $matchedIds)->update(['meta_resultado_id' => $meta->id]);
            }

            $products = MetaProducto::query()
                ->where('meta_resultado_id', $meta->id)
                ->with('subprograma')
                ->get();

            $subprogramaId = null;
            $programaId = null;

            if ($products->isEmpty()) {
                $withoutProducts++;
                $this->notes[] = ['sin_metas_producto', $meta->codigo_provisional, '', 'La fila de la matriz no dejó metas de producto de producción asociadas.'];
            } else {
                $subprogramaIds = $products->pluck('subprograma_id')->unique()->values();
                $programaIds = $products->map(fn (MetaProducto $product): ?int => $product->subprograma?->programa_id)->unique()->values();

                if ($subprogramaIds->count() === 1) {
                    $subprogramaId = (int) $subprogramaIds->first();
                    $programaId = (int) $products->first()->subprograma?->programa_id;
                } elseif ($programaIds->count() === 1 && $programaIds->first() !== null) {
                    $programaId = (int) $programaIds->first();
                }
            }

            if ($programaId === null) {
                $withoutPrograma++;
                $this->notes[] = ['sin_programa', $meta->codigo_provisional, '', 'Las metas de producto asociadas no comparten un solo programa.'];
            }

            $meta->programa_id = $programaId;
            $meta->subprograma_id = $subprogramaId;
            $meta->save();
        }

        $this->writeReport();

        $unmatched = count(array_filter($this->notes, fn (array $note): bool => $note[0] === 'codigo_sin_produccion'));

        $this->command?->info(sprintf(
            'Metas resultado: %d. Indicadores: %d. Metas producto vinculadas: %d. Códigos sin producción: %d. Metas sin metas producto: %d. Metas sin programa: %d. No se inventaron códigos ni se reasignaron equivalencias.',
            count($metas),
            count($indicators),
            $linked,
            $unmatched,
            $withoutProducts,
            $withoutPrograma,
        ));
    }

    /**
     * @return list<array<string, string>>
     */
    private function rows(): array
    {
        $path = database_path('seeders/data/metas_resultado_matriz.csv');
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('No se pudo leer la matriz de metas de resultado.');
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw new RuntimeException('La matriz de metas de resultado está vacía.');
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? $header[0];
        $header = array_map(fn (string $column): string => trim($column), $header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || implode('', $data) === '') {
                continue;
            }

            $row = [];

            foreach ($header as $index => $column) {
                $row[$column] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function indicators(array $rows): array
    {
        $indicators = [];

        foreach ($rows as $row) {
            $nombre = $this->clean($row['indicador_resultado_nombre'] ?? null);

            if ($nombre === null) {
                continue;
            }

            $candidate = [
                'unidad_medida' => $this->clean($row['indicador_unidad'] ?? null),
                'orientacion' => $this->orientacion($this->clean($row['indicador_orientacion'] ?? null), $nombre),
                'linea_base' => null,
                'linea_base_texto' => null,
                'meta_cuatrienio' => null,
                'fuente_verificacion' => null,
                'activo' => true,
            ];

            if (! isset($indicators[$nombre])) {
                $indicators[$nombre] = $candidate;

                continue;
            }

            foreach (['unidad_medida', 'orientacion'] as $attribute) {
                $current = $indicators[$nombre][$attribute];
                $incoming = $candidate[$attribute];

                if ($current === null && $incoming !== null) {
                    $indicators[$nombre][$attribute] = $incoming;
                } elseif ($current !== null && $incoming !== null && $current !== $incoming) {
                    $this->notes[] = ['indicador_atributo_distinto', '', $nombre, 'El atributo '.$attribute.' no coincide entre filas. Se conservó el primero.'];
                }
            }
        }

        return $indicators;
    }

    private function orientacion(?string $value, string $indicator): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(['ó', 'á', 'é', 'í', 'ú'], ['o', 'a', 'e', 'i', 'u'], mb_strtolower($value));

        return match ($normalized) {
            'incremento' => 'incremento',
            'reduccion' => 'reduccion',
            'mantenimiento' => 'mantenimiento',
            default => $this->unknownOrientacion($indicator, $value),
        };
    }

    private function unknownOrientacion(string $indicator, string $value): null
    {
        $this->notes[] = ['orientacion_desconocida', '', $indicator, 'Orientación no reconocida: '.$value.'. Quedó vacía.'];

        return null;
    }

    private function decimal(string $value, string $provisional, string $column): ?string
    {
        $clean = $this->clean($value);

        if ($clean === null) {
            return null;
        }

        $numeric = str_replace(',', '.', $clean);

        if (! is_numeric($numeric)) {
            $this->notes[] = ['valor_no_numerico', $provisional, $column, 'El valor «'.$clean.'» no es numérico y no se guardó.'];

            return null;
        }

        return $numeric;
    }

    /**
     * @return list<string>
     */
    private function productCodes(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $codes = [];

        foreach (explode('|', $value) as $code) {
            $code = trim($code);

            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $value === '' ? null : $value;
    }

    private function writeReport(): void
    {
        $directory = storage_path('app/seed-reports');
        File::ensureDirectoryExists($directory);
        $handle = fopen($directory.'/metas_resultado.csv', 'wb');

        if ($handle === false) {
            throw new RuntimeException('No se pudo escribir el reporte de metas de resultado.');
        }

        fputcsv($handle, ['tipo', 'codigo_provisional', 'referencia', 'detalle']);

        foreach ($this->notes as $note) {
            fputcsv($handle, $note);
        }

        fclose($handle);
    }
}
