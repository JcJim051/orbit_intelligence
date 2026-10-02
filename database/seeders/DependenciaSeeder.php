<?php

namespace Database\Seeders;

use App\Models\Dependencia;
use Illuminate\Database\Seeder;
use RuntimeException;

class DependenciaSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/dependencias.csv');
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('No se encontró el archivo de dependencias.');
        }

        $header = fgetcsv($handle);
        $header = is_array($header) ? array_map(fn ($column) => trim((string) $column), $header) : false;

        if ($header === false) {
            fclose($handle);

            throw new RuntimeException('El archivo de dependencias está vacío.');
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || count(array_filter($row, fn ($value) => $value !== null && $value !== '')) === 0) {
                continue;
            }

            $data = array_combine($header, $row);

            if ($data === false) {
                continue;
            }

            Dependencia::query()->updateOrCreate(
                ['codigo' => trim((string) $data['codigo'])],
                [
                    'nombre' => trim((string) $data['nombre']),
                    'sigla' => trim((string) $data['sigla']) ?: null,
                    'tipo' => trim((string) $data['tipo']),
                    'hoja_matriz' => trim((string) $data['hoja_matriz']) ?: null,
                    'activo' => trim((string) $data['activo']) === '1',
                ],
            );
        }

        fclose($handle);
    }
}
