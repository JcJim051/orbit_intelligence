<?php

namespace App\Services\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class CatalogWorkbook
{
    public function writeExport(CatalogDefinition $catalog, string $path): void
    {
        $query = $catalog->newQuery();
        $catalog->order($query);
        $rows = [];

        foreach ($query->get() as $record) {
            $line = [];

            foreach ($catalog->excelFields() as $field) {
                $value = $record->getAttribute($field->column);

                if ($field->input === 'dependencia') {
                    $dependencia = $record->getRelationValue('dependencia');
                    $line[] = $dependencia?->codigo;

                    continue;
                }

                if ($field->input === 'boolean') {
                    $line[] = $value ? 1 : 0;

                    continue;
                }

                if ($value instanceof \BackedEnum) {
                    $line[] = $value->value;

                    continue;
                }

                $line[] = $value;
            }

            $rows[] = $line;
        }

        $this->write($path, 'Datos', $catalog->excelHeaders(), $rows);
    }

    /**
     * @param  list<string>  $instructions
     */
    public function writeTemplate(CatalogDefinition $catalog, string $path): void
    {
        $this->write(
            $path,
            'Datos',
            $catalog->excelHeaders(),
            [$catalog->exampleRow()],
            $catalog->instructionLines(),
        );
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     * @param  list<string>  $instructions
     */
    public function write(string $path, string $sheetName, array $headers, array $rows, array $instructions = []): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($sheetName);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }

        if ($instructions !== []) {
            $writer->addNewSheetAndMakeItCurrent()->setName('Instrucciones');

            foreach ($instructions as $line) {
                $writer->addRow(Row::fromValues([$line]));
            }
        }

        $writer->close();
    }

    /**
     * @return array{headers: list<string>, rows: list<array{line: int, values: array<string, mixed>}>}
     */
    public function readTable(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $headers = null;
        $rows = [];
        $line = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $values = $this->trimTrailing($this->scalars($row->toArray()));

                    if ($headers === null) {
                        $headers = array_map(fn (mixed $value): string => trim((string) $value), $values);

                        continue;
                    }

                    $assoc = [];

                    foreach ($headers as $index => $header) {
                        if ($header === '') {
                            continue;
                        }

                        $assoc[$header] = $values[$index] ?? null;
                    }

                    $rows[] = ['line' => $line, 'values' => $assoc];
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($headers === null) {
            throw new RuntimeException('El archivo no tiene filas.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    public function namedSheets(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $this->trimTrailing($this->scalars($row->toArray()));
                }

                $sheets[$sheet->getName()] = $rows;
            }
        } finally {
            $reader->close();
        }

        return $sheets;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<mixed>
     */
    private function scalars(array $values): array
    {
        return array_map(function (mixed $value): mixed {
            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            if (is_float($value)) {
                return floor($value) == $value ? sprintf('%.0f', $value) : (string) $value;
            }

            if (is_string($value)) {
                $value = trim($value);

                return $value === '' ? null : $value;
            }

            return $value;
        }, $values);
    }

    /**
     * @param  list<mixed>  $values
     * @return list<mixed>
     */
    private function trimTrailing(array $values): array
    {
        while ($values !== [] && ($values[array_key_last($values)] === null || $values[array_key_last($values)] === '')) {
            array_pop($values);
        }

        return array_values($values);
    }
}
