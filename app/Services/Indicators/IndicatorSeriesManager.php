<?php

namespace App\Services\Indicators;

use App\Models\MicrositioIndicador;
use App\Models\TabularDataSource;
use App\Models\TabularDataSourceVersion;
use App\Models\User;
use App\Services\Dashboards\TabularFileReader;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class IndicatorSeriesManager
{
    public function __construct(private readonly TabularFileReader $reader) {}

    /** @return list<array{key:string,label:string,type:string,required:bool,visibility:string}> */
    public function schema(MicrositioIndicador $indicator): array
    {
        $indicator->loadMissing('dataSource.currentVersion');

        return collect($indicator->dataSource?->currentVersion?->fields ?? [])->map(fn (array $field): array => [
            'key' => (string) ($field['key'] ?? ''),
            'label' => (string) ($field['label'] ?? ''),
            'type' => (string) ($field['type'] ?? 'text'),
            'required' => (bool) ($field['required'] ?? false),
            'visibility' => 'public',
        ])->values()->all();
    }

    /** @param list<array<string, mixed>> $columns */
    public function saveSchema(MicrositioIndicador $indicator, array $columns, User $user): TabularDataSourceVersion
    {
        $fields = $this->normalizeSchema($columns);

        return DB::transaction(function () use ($indicator, $fields, $user): TabularDataSourceVersion {
            $source = $this->source($indicator, $user);
            $source->loadMissing('currentVersion');
            $records = collect($source->currentVersion?->records ?? [])->map(fn (array $record): array => collect($fields)->mapWithKeys(
                fn (array $field): array => [$field['key'] => $record[$field['key']] ?? null]
            )->all())->all();

            return $this->createVersion($source, $fields, $records, 'esquema-manual.xlsx', $user, [
                'schema_only' => true,
                'message' => 'Estructura definida manualmente.',
            ]);
        });
    }

    public function import(MicrositioIndicador $indicator, UploadedFile $file, User $user): TabularDataSourceVersion
    {
        $indicator->loadMissing('dataSource.currentVersion');
        $source = $indicator->dataSource;
        $schema = $this->schema($indicator);

        if (! $source || $schema === []) {
            throw ValidationException::withMessages(['seriesFile' => 'Primero guarde la estructura de columnas.']);
        }

        try {
            $parsed = $this->reader->read($file);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['seriesFile' => $exception->getMessage()]);
        }

        $expected = collect($schema)->pluck('key')->all();
        $received = collect($parsed['fields'])->pluck('key')->all();
        $missing = array_values(array_diff($expected, $received));
        $extra = array_values(array_diff($received, $expected));

        if ($missing !== [] || $extra !== []) {
            $messages = [];
            if ($missing !== []) {
                $messages[] = 'faltan: '.implode(', ', $missing);
            }
            if ($extra !== []) {
                $messages[] = 'sobran: '.implode(', ', $extra);
            }
            throw ValidationException::withMessages(['seriesFile' => 'Las columnas no coinciden con la plantilla ('.implode('; ', $messages).').']);
        }

        $errors = [];
        $records = collect($parsed['records'])->map(function (array $record, int $index) use ($schema, &$errors): array {
            return collect($schema)->mapWithKeys(function (array $field) use ($record, $index, &$errors): array {
                $value = $record[$field['key']] ?? null;
                if ($field['required'] && ($value === null || $value === '')) {
                    $errors[] = 'Fila '.($index + 2).": {$field['label']} es obligatorio.";
                }

                return [$field['key'] => $this->cast($value, $field, $index + 2, $errors)];
            })->all();
        })->all();

        if ($errors !== []) {
            throw ValidationException::withMessages(['seriesFile' => array_slice($errors, 0, 20)]);
        }

        return DB::transaction(fn (): TabularDataSourceVersion => $this->createVersion(
            $source, $schema, $records, $file->getClientOriginalName(), $user,
            [...$parsed['validation_summary'], 'schema_valid' => true],
            hash_file('sha256', $file->getRealPath()),
        ));
    }

    public function template(MicrositioIndicador $indicator): string
    {
        $schema = $this->schema($indicator);
        if ($schema === []) {
            throw ValidationException::withMessages(['columns' => 'Guarde la estructura antes de descargar la plantilla.']);
        }

        $path = storage_path('framework/testing/plantilla-serie-'.Str::slug($indicator->codigo).'-'.Str::random(8).'.xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(collect($schema)->pluck('key')->all()));
        $writer->close();

        return $path;
    }

    private function source(MicrositioIndicador $indicator, User $user): TabularDataSource
    {
        if ($indicator->tabular_data_source_id) {
            return $indicator->dataSource()->firstOrFail();
        }

        $base = 'indicador-'.Str::slug($indicator->codigo);
        $slug = $base;
        $suffix = 2;
        while (TabularDataSource::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        $source = TabularDataSource::query()->create([
            'name' => 'Serie de datos · '.$indicator->nombre,
            'slug' => $slug,
            'description' => 'Serie configurable del indicador '.$indicator->codigo.'.',
            'created_by' => $user->id,
            'current_version' => 0,
        ]);
        $indicator->update(['tabular_data_source_id' => $source->id]);
        $indicator->setRelation('dataSource', $source);

        return $source;
    }

    /** @param list<array<string,mixed>> $columns
     * @return list<array{key:string,label:string,type:string,required:bool,visibility:string}>
     */
    private function normalizeSchema(array $columns): array
    {
        $allowed = ['text', 'integer', 'decimal', 'date', 'boolean'];
        $fields = collect($columns)->map(function (array $column, int $index) use ($allowed): array {
            $key = Str::slug(Str::ascii(trim((string) ($column['key'] ?? ''))), '_');
            $label = trim((string) ($column['label'] ?? ''));
            $type = (string) ($column['type'] ?? 'text');
            if ($key === '' || $label === '') {
                throw ValidationException::withMessages(['columns.'.($index).'.label' => 'Cada columna debe tener nombre e identificador.']);
            }
            if (! in_array($type, $allowed, true)) {
                throw ValidationException::withMessages(['columns.'.($index).'.type' => 'Tipo de dato no válido.']);
            }

            return ['key' => $key, 'label' => $label, 'type' => $type, 'required' => (bool) ($column['required'] ?? false), 'visibility' => 'public'];
        })->values();

        if ($fields->isEmpty()) {
            throw ValidationException::withMessages(['columns' => 'Agregue al menos una columna.']);
        }
        if ($fields->pluck('key')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['columns' => 'Los identificadores de columna no pueden repetirse.']);
        }

        return $fields->all();
    }

    /** @param array<string,mixed> $field
     * @param  list<string>  $errors
     */
    private function cast(mixed $value, array $field, int $row, array &$errors): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        $invalid = function () use ($field, $row, &$errors): null {
            $errors[] = "Fila {$row}: {$field['label']} no corresponde al tipo {$field['type']}.";

            return null;
        };

        return match ($field['type']) {
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $invalid(),
            'decimal' => $this->decimal($value) ?? $invalid(),
            'date' => $this->date($value) ?? $invalid(),
            'boolean' => $this->boolean($value) ?? $invalid(),
            default => (string) $value,
        };
    }

    private function date(mixed $value): ?string
    {
        try {
            if (is_numeric($value) && (float) $value > 0 && (float) $value < 100000) {
                return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
            }

            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function decimal(mixed $value): ?float
    {
        $normalized = trim((string) $value);
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = strrpos($normalized, ',') > strrpos($normalized, '.')
                ? str_replace(['.', ','], ['', '.'], $normalized)
                : str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function boolean(mixed $value): ?bool
    {
        return match (mb_strtolower(trim((string) $value))) {
            '1', 'si', 'sí', 'true', 'verdadero' => true,
            '0', 'no', 'false', 'falso' => false,
            default => null,
        };
    }

    /** @param list<array<string,mixed>> $fields
     * @param  list<array<string,mixed>>  $records
     * @param  array<string,mixed>  $summary
     */
    private function createVersion(TabularDataSource $source, array $fields, array $records, string $filename, User $user, array $summary, ?string $checksum = null): TabularDataSourceVersion
    {
        $version = ((int) $source->current_version) + 1;
        $result = $source->versions()->create([
            'version' => $version,
            'original_filename' => $filename,
            'checksum' => $checksum ?? hash('sha256', json_encode([$fields, $records], JSON_THROW_ON_ERROR)),
            'row_count' => count($records),
            'fields' => $fields,
            'records' => $records,
            'validation_summary' => $summary,
            'created_by' => $user->id,
        ]);
        $source->update(['current_version' => $version]);

        return $result;
    }
}
