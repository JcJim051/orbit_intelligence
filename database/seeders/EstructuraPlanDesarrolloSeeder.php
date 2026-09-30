<?php

namespace Database\Seeders;

use App\Models\MetaProducto;
use App\Models\PddEje;
use App\Models\PddLinea;
use App\Models\PddPilar;
use App\Models\PddPrograma;
use App\Models\PddSubprograma;
use App\Models\SectorMga;
use App\Services\Intelligence\PlanLabel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

class EstructuraPlanDesarrolloSeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private array $structureNotes = [];

    public function run(): void
    {
        $path = database_path('seeders/data/estructura_plan_desarrollo.json');
        $rows = json_decode(File::get($path), true);

        if (! is_array($rows)) {
            throw new RuntimeException('No se pudo leer la estructura del plan de desarrollo.');
        }

        /** @var array<string, array{label: string, parent: ?string}> $pilares */
        $pilares = [];
        /** @var array<string, array{label: string, parent: ?string}> $ejes */
        $ejes = [];
        /** @var array<string, array{label: string, parent: ?string}> $lineas */
        $lineas = [];
        /** @var array<string, array{label: string, parent: ?string}> $programas */
        $programas = [];
        /** @var array<string, array{label: string, parents: array<string, int>}> $subprogramas */
        $subprogramas = [];
        /** @var array<string, string> $sectores */
        $sectores = [];

        foreach ($rows as $row) {
            $sectores[$row['sector_codigo']] = $row['sector'];
            $this->absorb($pilares, $row['pilar_codigo'], null, $row['pilar'], 'pilar');
            $this->absorb($ejes, $row['eje_codigo'], $row['pilar_codigo'], $row['eje'], 'eje');
            $this->absorb($lineas, $row['linea_codigo'], $row['eje_codigo'], $row['linea'], 'línea');
            $this->absorb($programas, $row['programa_codigo'], $row['linea_codigo'], $row['programa'], 'programa');
            $this->absorbSubprograma($subprogramas, $row['subprograma_codigo'], $row['programa_codigo'], $row['subprograma']);
        }

        foreach ($sectores as $codigo => $label) {
            SectorMga::query()->updateOrCreate(
                ['codigo' => (string) $codigo],
                ['nombre' => PlanLabel::sectorName($label), 'activo' => true],
            );
        }

        foreach ($pilares as $codigo => $entry) {
            $this->upsertNode(PddPilar::class, $codigo, $entry['label'], []);
        }

        $pilarIds = PddPilar::query()->pluck('id', 'codigo');

        foreach ($ejes as $codigo => $entry) {
            $this->upsertNode(PddEje::class, $codigo, $entry['label'], [
                'pilar_id' => $this->requiredId($pilarIds, (string) $entry['parent'], 'pilar'),
            ]);
        }

        $ejeIds = PddEje::query()->pluck('id', 'codigo');

        foreach ($lineas as $codigo => $entry) {
            $this->upsertNode(PddLinea::class, $codigo, $entry['label'], [
                'eje_id' => $this->requiredId($ejeIds, (string) $entry['parent'], 'eje'),
            ]);
        }

        $lineaIds = PddLinea::query()->pluck('id', 'codigo');

        foreach ($programas as $codigo => $entry) {
            $this->upsertNode(PddPrograma::class, $codigo, $entry['label'], [
                'linea_id' => $this->requiredId($lineaIds, (string) $entry['parent'], 'línea'),
            ]);
        }

        $programaIds = PddPrograma::query()->pluck('id', 'codigo');

        foreach ($subprogramas as $codigo => $entry) {
            $programaCodigo = $this->resolveProgramaCodigo($codigo, $entry, $programas);
            $this->upsertNode(PddSubprograma::class, $codigo, $entry['label'], [
                'programa_id' => $this->requiredId($programaIds, $programaCodigo, 'programa'),
            ]);
        }

        $subprogramaIds = PddSubprograma::query()->pluck('id', 'codigo');
        $sectorIds = SectorMga::query()->pluck('id', 'codigo');

        foreach ($rows as $row) {
            MetaProducto::query()->updateOrCreate(
                ['codigo' => $row['codigo_meta_plan']],
                [
                    'nombre' => trim(preg_replace('/\s+/u', ' ', (string) $row['nombre_meta_plan']) ?? ''),
                    'subprograma_id' => $this->requiredId($subprogramaIds, $row['subprograma_codigo'], 'subprograma'),
                    'sector_mga_id' => $this->requiredId($sectorIds, (string) $row['sector_codigo'], 'sector'),
                    'activo' => (bool) $row['activo'],
                ],
            );
        }

        $this->writeStructureReport();

        $this->command?->info(sprintf(
            'Estructura: pilares %d, ejes %d, líneas %d, programas %d, subprogramas %d, sectores %d, metas producto %d. Ajustes de padre: %d.',
            count($pilares),
            count($ejes),
            count($lineas),
            count($programas),
            count($subprogramas),
            count($sectores),
            count($rows),
            count($this->structureNotes),
        ));
    }

    /**
     * @param  array<string, array{label: string, parent: ?string}>  $bucket
     */
    private function absorb(array &$bucket, string $code, ?string $parent, string $label, string $level): void
    {
        if (! isset($bucket[$code])) {
            $bucket[$code] = ['label' => $label, 'parent' => $parent];

            return;
        }

        if ($bucket[$code]['parent'] !== $parent) {
            throw new RuntimeException("El {$level} {$code} aparece con padres distintos en la exportación.");
        }
    }

    /**
     * @param  array<string, array{label: string, parents: array<string, int>}>  $bucket
     */
    private function absorbSubprograma(array &$bucket, string $code, string $parent, string $label): void
    {
        if (! isset($bucket[$code])) {
            $bucket[$code] = ['label' => $label, 'parents' => []];
        }

        $bucket[$code]['parents'][$parent] = ($bucket[$code]['parents'][$parent] ?? 0) + 1;
    }

    /**
     * @param  array{label: string, parents: array<string, int>}  $entry
     * @param  array<string, array{label: string, parent: ?string}>  $programas
     */
    private function resolveProgramaCodigo(string $subCodigo, array $entry, array $programas): string
    {
        $parents = $entry['parents'];

        if (count($parents) === 1) {
            return (string) array_key_first($parents);
        }

        $structural = array_values(array_filter(
            array_keys($parents),
            fn (string $code): bool => str_ends_with($code, '00000'),
        ));

        if (count($structural) === 1) {
            $this->noteParents($subCodigo, $structural[0], $parents, 'Se conservó el programa de código estructural y se descartó el que repite el texto de una meta.');

            return $structural[0];
        }

        $parsed = PlanLabel::split($entry['label']);

        if ($parsed['numeral'] !== null && str_contains($parsed['numeral'], '.')) {
            $parentNumeral = implode('.', array_slice(explode('.', $parsed['numeral']), 0, -1));

            foreach ($programas as $code => $programa) {
                if (PlanLabel::split($programa['label'])['numeral'] === $parentNumeral) {
                    $this->noteParents($subCodigo, (string) $code, $parents, 'Ningún padre repetido era estructural. Se usó el programa cuyo numeral es el prefijo del subprograma.');

                    return (string) $code;
                }
            }
        }

        arsort($parents);
        $chosen = (string) array_key_first($parents);
        $this->noteParents($subCodigo, $chosen, $parents, 'Se conservó el programa que más se repetía.');

        return $chosen;
    }

    /**
     * @param  array<string, int>  $parents
     */
    private function noteParents(string $subCodigo, string $chosen, array $parents, string $detalle): void
    {
        foreach ($parents as $code => $count) {
            if ((string) $code === $chosen) {
                continue;
            }

            $this->structureNotes[] = [
                'subprograma_padre_ajustado',
                $subCodigo,
                (string) $code,
                $detalle.' Programa conservado: '.$chosen.'. Filas con el código descartado: '.$count.'.',
            ];
        }

        if (! array_key_exists($chosen, $parents)) {
            $this->structureNotes[] = [
                'subprograma_padre_por_numeral',
                $subCodigo,
                $chosen,
                $detalle,
            ];
        }
    }

    /**
     * @param  class-string  $model
     * @param  array<string, int>  $foreignKeys
     */
    private function upsertNode(string $model, string $codigo, string $label, array $foreignKeys): void
    {
        $parsed = PlanLabel::split($label);

        $model::query()->updateOrCreate(
            ['codigo' => $codigo],
            [
                'numeral' => $parsed['numeral'],
                'nombre' => $parsed['nombre'],
                'activo' => true,
                ...$foreignKeys,
            ],
        );
    }

    /**
     * @param  Collection<string, int>  $ids
     */
    private function requiredId($ids, string $codigo, string $level): int
    {
        $id = $ids[$codigo] ?? null;

        if ($id === null) {
            throw new RuntimeException("No se encontró el {$level} {$codigo}.");
        }

        return (int) $id;
    }

    private function writeStructureReport(): void
    {
        $directory = storage_path('app/seed-reports');
        File::ensureDirectoryExists($directory);
        $handle = fopen($directory.'/estructura_plan.csv', 'wb');

        if ($handle === false) {
            throw new RuntimeException('No se pudo escribir el reporte de la estructura.');
        }

        fputcsv($handle, ['tipo', 'codigo', 'referencia', 'detalle']);

        foreach ($this->structureNotes as $note) {
            fputcsv($handle, $note);
        }

        fclose($handle);
    }
}
