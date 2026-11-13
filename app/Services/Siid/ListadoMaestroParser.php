<?php

namespace App\Services\Siid;

use Illuminate\Support\Str;

class ListadoMaestroParser
{
    private const ALIASES = [
        'codigo' => ['codigo', 'código', 'cod indicador', 'codigo indicador', 'id indicador', 'ficha', 'ficha tecnica', 'ficha_tecnica'],
        'nombre' => ['nombre', 'indicador', 'nombre indicador'],
        'area' => ['area', 'área', 'area tematica', 'área temática', 'tematica'],
        'descripcion' => ['descripcion', 'descripción', 'descripcion del indicador', 'descripción del indicador', 'definicion', 'definición'],
        'fuente' => ['fuente', 'fuente informacion', 'fuente de información'],
        'url_fuente' => ['url fuente', 'url_fuente', 'enlace fuente', 'enlace de la fuente', 'link fuente', 'link de la fuente'],
        'unidad_medida' => ['unidad', 'unidad de medida'],
        'periodicidad' => ['periodicidad', 'frecuencia'],
        'url_ficha' => ['url ficha', 'url_ficha'],
        'url_datos' => ['url indicador', 'url_indicador', 'url datos', 'url_datos'],
        'url_tablero' => ['url tablero', 'url_tablero', 'enlace tablero', 'enlace del tablero', 'uuid tablero', 'uuid del tablero', 'enlace o uuid del tablero', 'url o uuid del tablero'],
        'tipo_tablero' => ['tipo tablero', 'tipo_tablero'],
        'estado_listado' => ['estado', 'estado listado'],
        'procedencia' => ['procedencia', 'origen'],
        'ods_codigo' => ['ods', 'codigo ods', 'indicador ods'],
        'ods_nombre' => ['nombre ods', 'descripcion ods'],
        'icet' => ['icet'],
        'pet' => ['pet'],
    ];

    /** @param array<int, array<int, mixed>> $rows
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function parse(string $sheet, array $rows): array
    {
        $headerIndex = $this->headerIndex($rows);
        if ($headerIndex === null) {
            return ['rows' => [], 'errors' => ["{$sheet}: no se encontró una fila de encabezados con código y nombre."]];
        }

        $headers = collect($rows[$headerIndex])->map(fn (mixed $value): string => $this->normalize((string) $value))->all();
        $mapped = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $index = array_search($this->normalize($alias), $headers, true);
                if ($index !== false) {
                    $mapped[$field] = $index;
                    break;
                }
            }
        }

        $parsed = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            $record = ['_sheet' => $sheet, '_row' => $index + 1];
            foreach ($mapped as $field => $column) {
                $record[$field] = isset($row[$column]) ? trim((string) $row[$column]) : null;
            }
            if (collect($record)->except(['_sheet', '_row'])->filter(fn ($value): bool => filled($value))->isNotEmpty()) {
                $parsed[] = $record;
            }
        }

        return ['rows' => $parsed, 'errors' => []];
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function headerIndex(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 30, true) as $index => $row) {
            $normalized = collect($row)->map(fn (mixed $value): string => $this->normalize((string) $value));
            $hasCode = $normalized->intersect(collect(self::ALIASES['codigo'])->map(fn (string $v): string => $this->normalize($v)))->isNotEmpty();
            $hasName = $normalized->intersect(collect(self::ALIASES['nombre'])->map(fn (string $v): string => $this->normalize($v)))->isNotEmpty();
            if ($hasCode && $hasName) {
                return $index;
            }
        }

        return null;
    }

    public function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }
}
