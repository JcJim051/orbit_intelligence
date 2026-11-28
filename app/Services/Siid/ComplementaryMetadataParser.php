<?php

namespace App\Services\Siid;

use Illuminate\Support\Str;

class ComplementaryMetadataParser
{
    public function __construct(private readonly ListadoMaestroParser $normalizer) {}

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, array<int, array<int, mixed>>>  $sheets
     * @return array<int, array<string, mixed>>
     */
    public function enrich(array $records, array $sheets): array
    {
        $catalogMetadata = [];
        $odsMetadata = [];

        foreach ($sheets as $sheet => $rows) {
            if ($this->normalizer->normalize($sheet) === 'ods') {
                $odsMetadata = $this->parseOds($rows);

                continue;
            }
            array_push($catalogMetadata, ...$this->parseCatalog($rows));
        }

        foreach ($records as &$record) {
            $code = $this->code((string) ($record['codigo'] ?? ''));
            $name = $this->normalizer->normalize((string) ($record['nombre'] ?? ''));
            $catalog = collect($catalogMetadata)->first(
                fn (array $item): bool => ($item['_code'] !== '' && $item['_code'] === $code)
                    || ($item['_name'] !== '' && $item['_name'] === $name)
            );
            if ($catalog) {
                $record = $this->mergeMissing($record, $catalog);
            }

            $relations = collect($odsMetadata)->filter(
                fn (array $item): bool => ($item['_sheet_code'] !== '' && $item['_sheet_code'] === $code)
                    || ($item['_siid_name'] !== '' && $item['_siid_name'] === $name)
            )->values()->all();
            if ($relations !== []) {
                $record = $this->mergeMissing($record, $relations[0]);
                $record['ods_relaciones'] = array_map(fn (array $item): array => $item['ods_relation'], $relations);
            }
        }
        unset($record);

        return $records;
    }

    /** @param array<int, array<int, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function parseCatalog(array $rows): array
    {
        [$headerIndex, $columns] = $this->headers($rows, ['indicador', 'ficha tecnica indicador']);
        if ($headerIndex === null) {
            return [];
        }

        $result = [];
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $name = $this->cell($row, $columns, ['indicador']);
            $code = $this->cell($row, $columns, ['ficha tecnica indicador']);
            if ($name === '' || $code === '') {
                continue;
            }
            $source = $this->cell($row, $columns, ['fuentes', 'fuente']);
            $result[] = [
                '_code' => $this->code($code),
                '_name' => $this->normalizer->normalize($name),
                'fuente' => $source ?: null,
                'fuentes_complementarias' => $source ?: null,
                'anio_actualizacion' => $this->cell($row, $columns, ['ano actualizacion', 'ano de actualizacion']) ?: null,
                'icet' => $this->cell($row, $columns, ['icet']) ?: null,
                'pet' => $this->cell($row, $columns, ['pet']) ?: null,
                'ods_marcado' => $this->cell($row, $columns, ['ods']) ?: null,
            ];
        }

        return $result;
    }

    /** @param array<int, array<int, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function parseOds(array $rows): array
    {
        [$headerIndex, $columns] = $this->headers($rows, ['codigo del indicador', 'indicador siid']);
        if ($headerIndex === null) {
            return [];
        }

        $result = [];
        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $siidName = $this->cell($row, $columns, ['indicador siid']);
            $sheetCode = $this->cell($row, $columns, ['ficha']);
            $odsIndicatorCode = $this->cell($row, $columns, ['codigo del indicador']);
            if ($siidName === '' && $sheetCode === '') {
                continue;
            }
            $source = $this->cell($row, $columns, ['fuente de informacion']);
            $result[] = [
                '_sheet_code' => $this->code($sheetCode),
                '_siid_name' => $this->normalizer->normalize($siidName),
                'descripcion' => $this->cell($row, $columns, ['explicacion del indicador']) ?: null,
                'periodicidad' => $this->cell($row, $columns, ['frecuencia']) ?: null,
                'unidad_medida' => $this->cell($row, $columns, ['unidad de medida']) ?: null,
                'fuente' => $source ?: null,
                'ods_relation' => [
                    'codigo' => $odsIndicatorCode ?: null,
                    'nombre' => $this->cell($row, $columns, ['nombre del indicador']) ?: null,
                    'meta' => $this->cell($row, $columns, ['meta del ods']) ?: null,
                    'ods_codigo' => $this->cell($row, $columns, ['codigo del ods']) ?: null,
                    'ods_nombre' => $this->cell($row, $columns, ['nombre del ods']) ?: null,
                ],
            ];
        }

        return $result;
    }

    /** @param array<int, array<int, mixed>> $rows
     * @param  list<string>  $required
     * @return array{0: ?int, 1: array<string, int>}
     */
    private function headers(array $rows, array $required): array
    {
        foreach (array_slice($rows, 0, 30, true) as $index => $row) {
            $columns = [];
            foreach ($row as $column => $value) {
                $columns[$this->normalizer->normalize((string) $value)] = $column;
            }
            if (collect($required)->every(fn (string $header): bool => array_key_exists($header, $columns))) {
                return [$index, $columns];
            }
        }

        return [null, []];
    }

    /** @param array<int, mixed> $row
     * @param  array<string, int>  $columns
     * @param  list<string>  $aliases
     */
    private function cell(array $row, array $columns, array $aliases): string
    {
        foreach ($aliases as $alias) {
            if (isset($columns[$alias])) {
                return trim((string) ($row[$columns[$alias]] ?? ''));
            }
        }

        return '';
    }

    /** @param array<string, mixed> $record
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function mergeMissing(array $record, array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (! str_starts_with($key, '_') && $key !== 'ods_relation' && filled($value) && blank($record[$key] ?? null)) {
                $record[$key] = $value;
            }
        }

        return $record;
    }

    private function code(string $value): string
    {
        return Str::upper(preg_replace('/\s+/', '', trim($value)));
    }
}
