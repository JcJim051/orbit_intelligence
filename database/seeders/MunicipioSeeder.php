<?php

namespace Database\Seeders;

use App\Models\Municipio;
use Illuminate\Database\Seeder;
use RuntimeException;

class MunicipioSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/municipios_meta.csv');
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('No se encontró el archivo de municipios.');
        }

        $header = fgetcsv($handle);
        $header = is_array($header) ? array_map(fn ($column) => trim((string) $column), $header) : false;

        if ($header === false) {
            fclose($handle);

            throw new RuntimeException('El archivo de municipios está vacío.');
        }

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || count(array_filter($row, fn ($value) => $value !== null && $value !== '')) === 0) {
                continue;
            }

            $data = array_combine($header, $row);

            if ($data === false) {
                continue;
            }

            Municipio::query()->updateOrCreate(
                ['codigo_dane' => trim((string) $data['codigo_dane'])],
                [
                    'nombre' => trim((string) $data['nombre']),
                    'subregion' => trim((string) $data['subregion']) ?: null,
                    'activo' => trim((string) $data['activo']) === '1',
                ],
            );
        }

        fclose($handle);
    }
}
