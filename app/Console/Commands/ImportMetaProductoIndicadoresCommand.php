<?php

namespace App\Console\Commands;

use App\Models\MetaProducto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ImportMetaProductoIndicadoresCommand extends Command
{
    protected $signature = 'metas-producto:import-indicadores
        {archivo : Ruta del CSV o XLSX con codigo_meta_producto e indicador_producto}
        {--dry-run : Valida y muestra el resumen sin escribir cambios}';

    protected $description = 'Actualiza únicamente el indicador producto de cada meta producto desde un CSV/XLSX.';

    public function handle(): int
    {
        $path = (string) $this->argument('archivo');

        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readRows($path);

        if ($rows === []) {
            $this->error('El archivo no tiene filas para importar.');

            return self::FAILURE;
        }

        $metaHeaders = ['codigo_meta_producto', 'meta_producto_codigo', 'codigo_meta_plan', 'cod_meta_producto', 'codigo'];
        $indicatorHeaders = ['indicador_producto', 'indicador_de_producto', 'indicador', 'nombre_indicador_producto'];
        $headers = array_map(fn (mixed $value): string => $this->normalizeHeader((string) $value), array_shift($rows));

        $metaIndex = $this->firstHeaderIndex($headers, $metaHeaders);
        $indicatorIndex = $this->firstHeaderIndex($headers, $indicatorHeaders);

        if ($metaIndex === null || $indicatorIndex === null) {
            $this->error('El archivo debe tener columnas codigo_meta_producto e indicador_producto.');

            return self::FAILURE;
        }

        $updates = [];
        $errors = [];

        foreach ($rows as $number => $row) {
            $line = $number + 2;
            $metaCode = trim((string) ($row[$metaIndex] ?? ''));
            $indicator = trim(preg_replace('/\s+/u', ' ', (string) ($row[$indicatorIndex] ?? '')) ?? '');

            if ($metaCode === '' && $indicator === '') {
                continue;
            }

            if ($metaCode === '' || $indicator === '') {
                $errors[] = "Fila {$line}: código de meta o indicador producto vacío.";

                continue;
            }

            $meta = MetaProducto::query()->withoutGlobalScopes()->where('codigo', $metaCode)->first();

            if (! $meta) {
                $errors[] = "Fila {$line}: no existe la meta producto {$metaCode}.";

                continue;
            }

            if ((string) $meta->indicador_producto === $indicator) {
                continue;
            }

            $updates[] = [
                'meta' => $meta,
                'indicador_producto' => $indicator,
            ];
        }

        if ($errors !== []) {
            foreach (array_slice($errors, 0, 25) as $error) {
                $this->error($error);
            }

            if (count($errors) > 25) {
                $this->error('... y '.(count($errors) - 25).' error(es) más.');
            }

            $this->warn('No se escribió ningún cambio.');

            return self::FAILURE;
        }

        $this->info('Filas con cambio: '.count($updates));

        if ($this->option('dry-run')) {
            $this->warn('Dry-run activo: no se escribió ningún cambio.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as $update) {
                /** @var MetaProducto $meta */
                $meta = $update['meta'];
                $meta->forceFill(['indicador_producto' => $update['indicador_producto']])->save();
            }
        });

        $this->info('Indicadores producto actualizados correctamente.');

        return self::SUCCESS;
    }

    /**
     * @return list<list<mixed>>
     */
    private function readRows(string $path): array
    {
        $reader = $this->reader($path);
        $reader->open($path);
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(
                        fn (mixed $value): mixed => $value instanceof Cell ? $value->getValue() : $value,
                        $row->toArray(),
                    );

                    if (collect($values)->filter(fn (mixed $value): bool => trim((string) $value) !== '')->isEmpty()) {
                        continue;
                    }

                    $rows[] = $values;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private function reader(string $path): ReaderInterface
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            return new XlsxReader;
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            return new CsvReader($this->csvOptions($this->delimiter($path)));
        }

        throw new \InvalidArgumentException('Formato no admitido. Use CSV o XLSX.');
    }

    private function csvOptions(string $delimiter): CsvOptions
    {
        if ((new \ReflectionClass(CsvOptions::class))->getConstructor() !== null) {
            return new CsvOptions(FIELD_DELIMITER: $delimiter);
        }

        $options = new CsvOptions;
        $options->FIELD_DELIMITER = $delimiter;

        return $options;
    }

    private function delimiter(string $path): string
    {
        $sample = (string) file_get_contents($path, false, null, 0, 8192);
        $counts = [';' => substr_count($sample, ';'), ',' => substr_count($sample, ','), "\t" => substr_count($sample, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $candidates
     */
    private function firstHeaderIndex(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers, true);

            if ($index !== false) {
                return (int) $index;
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        return (string) Str::of(Str::ascii($header))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }
}
