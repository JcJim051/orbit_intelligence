<?php

namespace Database\Seeders;

use App\Models\MetaProducto;
use App\Models\Seguimiento4mMeta;
use Illuminate\Database\Seeder;
use RuntimeException;

class SeguimientoCuatrienioSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/cuatrienio_2024_2025.csv');

        if (! is_file($path)) {
            throw new RuntimeException('No se encontró el archivo de carga del cuatrienio: '.$path);
        }

        $metas = MetaProducto::query()->pluck('id', 'codigo');
        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle);
        $required = ['codigo_meta_producto', 'vigencia', 'fila_fuente', 'responsable', 'orientacion', 'programado_fisico', 'ejecutado_fisico', 'asignado', 'comprometido'];

        if ($header !== false && isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        if ($header === false || array_values($header) !== $required) {
            throw new RuntimeException('El archivo del cuatrienio no tiene la estructura esperada.');
        }

        $now = now();
        $rows = [];
        $unlinked = 0;

        while (($values = fgetcsv($handle)) !== false) {
            if (count($values) !== count($required)) {
                continue;
            }

            $data = array_combine($required, $values);
            $codigo = trim((string) $data['codigo_meta_producto']);
            $vigencia = (int) $data['vigencia'];
            if ($codigo === '' || ! in_array($vigencia, [2024, 2025], true)) {
                continue;
            }

            $metaId = $metas[$codigo] ?? null;
            $unlinked += $metaId ? 0 : 1;
            $rows[] = [
                'meta_producto_id' => $metaId,
                'codigo_meta_producto' => $codigo,
                'vigencia' => $vigencia,
                'fila_fuente' => (int) $data['fila_fuente'],
                'responsable' => trim((string) $data['responsable']) ?: null,
                'orientacion' => trim((string) $data['orientacion']) ?: null,
                'programado_fisico' => $this->number($data['programado_fisico']),
                'ejecutado_fisico' => $this->number($data['ejecutado_fisico']),
                'asignado' => $this->number($data['asignado']),
                'comprometido' => $this->number($data['comprometido']),
                'obligado' => null,
                'pagado' => null,
                'estado_obligado' => 'No reportado',
                'estado_pagado' => 'No reportado',
                'fuente' => 'cuatrienio_2024_2025.csv',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        fclose($handle);

        foreach (array_chunk($rows, 200) as $chunk) {
            Seguimiento4mMeta::upsert(
                $chunk,
                ['codigo_meta_producto', 'vigencia', 'fila_fuente'],
                ['meta_producto_id', 'responsable', 'orientacion', 'programado_fisico', 'ejecutado_fisico', 'asignado', 'comprometido', 'obligado', 'pagado', 'estado_obligado', 'estado_pagado', 'fuente', 'updated_at'],
            );
        }

        $this->command?->info(sprintf('Cuatrienio cargado: %d filas; %d sin vínculo con metas_producto.', count($rows), $unlinked));
    }

    private function number(string $value): ?float
    {
        $value = trim($value);
        return $value === '' ? null : (float) str_replace(',', '.', $value);
    }
}
