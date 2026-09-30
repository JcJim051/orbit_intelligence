<?php

namespace App\Services\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Exception\OpenSpoutException;
use RuntimeException;

class CatalogImporter
{
    public function __construct(private CatalogWorkbook $workbook) {}

    public function import(CatalogDefinition $catalog, string $path): CatalogImportResult
    {
        try {
            $sheet = $this->workbook->readTable($path);
        } catch (OpenSpoutException|RuntimeException) {
            return CatalogImportResult::failed(['No se pudo leer el archivo de Excel. Use la plantilla .xlsx.']);
        }

        if ($sheet['headers'] !== $catalog->excelHeaders()) {
            return CatalogImportResult::failed([
                'La primera fila debe contener exactamente estas columnas, en este orden: '.implode(', ', $catalog->excelHeaders()).'.',
            ]);
        }

        if (count($sheet['rows']) > 5000) {
            return CatalogImportResult::failed(['El archivo supera el máximo de 5000 filas.']);
        }

        $prepared = [];
        $errors = [];
        $seen = [];

        foreach ($sheet['rows'] as $row) {
            if ($this->isBlank($row['values'])) {
                continue;
            }

            $normalized = $catalog->normalize($row['values'], excel: true);
            $fingerprint = $catalog->excelFingerprint($normalized);

            if (isset($seen[$fingerprint])) {
                $errors[] = 'Fila '.$row['line'].': repite la llave de la fila '.$seen[$fingerprint].'.';

                continue;
            }

            $seen[$fingerprint] = $row['line'];
            $validator = $catalog->makeValidator($normalized, excel: true);

            if ($validator->fails()) {
                $errors[] = 'Fila '.$row['line'].': '.implode(' ', $validator->errors()->all());

                continue;
            }

            $prepared[] = $catalog->attributesFrom($normalized, excel: true);
        }

        if ($errors !== []) {
            return CatalogImportResult::failed($errors);
        }

        $created = 0;
        $updated = 0;

        try {
            DB::transaction(function () use ($catalog, $prepared, &$created, &$updated): void {
                foreach ($prepared as $attributes) {
                    $existing = $this->findExisting($catalog, $attributes);

                    if ($existing instanceof Model) {
                        if (method_exists($existing, 'trashed') && $existing->trashed()) {
                            $existing->restore();
                        }

                        $existing->fill(Arr::except($attributes, $catalog->naturalKey()))->save();
                        $updated++;

                        continue;
                    }

                    $catalog->modelClass()::query()->create($attributes);
                    $created++;
                }
            });
        } catch (QueryException) {
            return CatalogImportResult::failed(['No se pudo guardar el archivo. No se aplicó ningún cambio.']);
        }

        return new CatalogImportResult(true, $created, $updated);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function findExisting(CatalogDefinition $catalog, array $attributes): ?Model
    {
        $model = $catalog->modelClass();
        $query = $model::query();

        if ($catalog->usesSoftDeletes()) {
            $query->withTrashed();
        }

        foreach ($catalog->naturalKey() as $column) {
            $query->where($column, $attributes[$column] ?? null);
        }

        return $query->first();
    }
}
