<?php

namespace App\Services\Dashboards;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class TabularFileReader
{
    /** @return array{fields: array<int, array<string, mixed>>, records: array<int, array<string, mixed>>, validation_summary: array<string, mixed>} */
    public function read(UploadedFile $file): array
    {
        $rows = match (mb_strtolower($file->getClientOriginalExtension())) {
            'csv' => $this->readCsv($file->getRealPath()),
            'xlsx' => $this->readXlsx($file->getRealPath()),
            default => throw new RuntimeException('El archivo debe ser CSV o XLSX.'),
        };

        if (count($rows) < 2) {
            throw new RuntimeException('El archivo debe contener encabezados y por lo menos una fila de datos.');
        }

        $headers = array_map(fn ($value): string => trim((string) $value), array_shift($rows));
        $keys = $this->uniqueKeys($headers);
        $records = collect($rows)->filter(fn (array $row): bool => collect($row)->contains(fn ($value): bool => $value !== null && $value !== ''))
            ->take(10000)
            ->map(function (array $row) use ($keys): array {
                $row = array_pad($row, count($keys), null);

                return array_combine($keys, array_slice($row, 0, count($keys)));
            })->values()->all();

        $fields = collect($keys)->map(function (string $key, int $index) use ($headers, $records): array {
            $values = array_column($records, $key);

            return [
                'key' => $key,
                'label' => $headers[$index] ?: Str::headline($key),
                'type' => $this->inferType($values),
                'visibility' => 'analytics',
            ];
        })->all();

        $validationSummary = $this->populationValidation($records, $keys);

        return ['fields' => $fields, 'records' => $records, 'validation_summary' => $validationSummary];
    }

    /** @return array<int, array<int, mixed>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No fue posible leer el archivo CSV.');
        }
        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = array_map(fn ($value) => $this->normalizeValue($value), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<int, array<int, mixed>> */
    private function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('El servidor no tiene habilitada la extensión ZIP necesaria para leer XLSX.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('No fue posible abrir el archivo XLSX.');
        }
        $shared = $this->sharedStrings($zip);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException('El XLSX no contiene una primera hoja legible.');
        }
        $sheet = new SimpleXMLElement($xml);
        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($sheet->xpath('//x:sheetData/x:row') ?: [] as $row) {
            $values = [];
            foreach ($row->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->c as $cell) {
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $match);
                $column = $this->columnIndex($match[0] ?? 'A');
                $type = (string) $cell['t'];
                $children = $cell->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $value = $type === 'inlineStr' ? (string) $children->is->t : (string) $children->v;
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $values[$column] = $this->normalizeValue($value);
            }
            if ($values !== []) {
                ksort($values);
                $rows[] = array_replace(array_fill(0, max(array_keys($values)) + 1, null), $values);
            }
        }

        return $rows;
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $strings = new SimpleXMLElement($xml);
        $strings->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return array_map(fn (SimpleXMLElement $item): string => implode('', array_map('strval', $item->xpath('.//x:t') ?: [])), $strings->xpath('//x:si') ?: []);
    }

    /** @param array<int, string> $headers
     * @return array<int, string>
     */
    private function uniqueKeys(array $headers): array
    {
        $used = [];

        return array_map(function (string $header, int $index) use (&$used): string {
            $base = Str::snake(Str::ascii($header)) ?: 'campo_'.($index + 1);
            $key = $base;
            $suffix = 2;
            while (isset($used[$key])) {
                $key = $base.'_'.$suffix++;
            }
            $used[$key] = true;

            return $key;
        }, $headers, array_keys($headers));
    }

    /** @param array<int, mixed> $values */
    private function inferType(array $values): string
    {
        $values = array_values(array_filter($values, fn ($value): bool => $value !== null && $value !== ''));
        if ($values === []) {
            return 'text';
        }
        if (collect($values)->every(fn ($value): bool => filter_var($value, FILTER_VALIDATE_INT) !== false)) {
            return 'integer';
        }
        if (collect($values)->every(fn ($value): bool => is_numeric(str_replace(',', '.', (string) $value)))) {
            return 'decimal';
        }
        if (collect($values)->every(fn ($value): bool => strtotime((string) $value) !== false)) {
            return 'date';
        }

        return 'text';
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $value = trim($value);

        return $value === '' ? null : mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }

    /** @param array<int, array<string, mixed>> $records
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function populationValidation(array $records, array $keys): array
    {
        $checks = [
            ['total' => 'poblacion_total', 'parts' => ['poblacion_femenina', 'poblacion_masculina'], 'label' => 'sexo'],
            ['total' => 'poblacion_total', 'parts' => ['poblacion_rural', 'poblacion_urbana'], 'label' => 'zona'],
        ];
        $mismatches = [];
        foreach ($checks as $check) {
            if (array_diff([$check['total'], ...$check['parts']], $keys) !== []) {
                continue;
            }
            foreach ($records as $index => $record) {
                $total = (float) ($record[$check['total']] ?? 0);
                $parts = array_sum(array_map(fn (string $field): float => (float) ($record[$field] ?? 0), $check['parts']));
                if (abs($total - $parts) > 0.01) {
                    $mismatches[] = ['row' => $index + 2, 'check' => $check['label'], 'total' => $total, 'parts_total' => $parts];
                }
            }
        }

        return ['population_mismatches' => array_slice($mismatches, 0, 100), 'population_mismatch_count' => count($mismatches)];
    }
}
