<?php

namespace App\Console\Commands;

use App\Models\Dependencia;
use App\Models\MetaProducto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ImportMetaProductoDependenciasCommand extends Command
{
    protected $signature = 'metas-producto:import-dependencias
        {archivo : Ruta del CSV o XLSX con codigo_meta_producto y dependencia_codigo}
        {--dry-run : Valida y muestra el resumen sin escribir cambios}';

    protected $description = 'Actualiza únicamente la dependencia responsable de cada meta producto desde un CSV/XLSX.';

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

        $metaHeaders = ['codigo_meta_producto', 'meta_producto_codigo', 'codigo_meta_plan', 'codigo'];
        $dependenciaHeaders = ['dependencia_codigo', 'codigo_dependencia', 'dependencia'];
        $headers = array_map(fn (mixed $value): string => $this->normalizeHeader((string) $value), array_shift($rows));

        $metaIndex = $this->firstHeaderIndex($headers, $metaHeaders);
        $dependenciaIndex = $this->firstHeaderIndex($headers, $dependenciaHeaders);

        if ($metaIndex === null || $dependenciaIndex === null) {
            $this->error('El archivo debe tener columnas codigo_meta_producto y dependencia_codigo.');

            return self::FAILURE;
        }

        $updates = [];
        $errors = [];

        foreach ($rows as $number => $row) {
            $line = $number + 2;
            $metaCode = trim((string) ($row[$metaIndex] ?? ''));
            $dependenciaCode = trim((string) ($row[$dependenciaIndex] ?? ''));

            if ($metaCode === '' && $dependenciaCode === '') {
                continue;
            }

            if ($metaCode === '' || $dependenciaCode === '') {
                $errors[] = "Fila {$line}: código de meta o dependencia vacío.";

                continue;
            }

            $meta = MetaProducto::query()->withoutGlobalScopes()->where('codigo', $metaCode)->first();
            $dependencia = Dependencia::query()->withoutGlobalScopes()->where('codigo', $dependenciaCode)->first();

            if (! $meta) {
                $errors[] = "Fila {$line}: no existe la meta producto {$metaCode}.";

                continue;
            }

            if (! $dependencia) {
                $errors[] = "Fila {$line}: no existe la dependencia {$dependenciaCode}.";

                continue;
            }

            if ((int) $meta->dependencia_id === (int) $dependencia->id) {
                continue;
            }

            $updates[] = [
                'meta' => $meta,
                'dependencia' => $dependencia,
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
                /** @var Dependencia $dependencia */
                $dependencia = $update['dependencia'];

                $meta->forceFill(['dependencia_id' => $dependencia->id])->save();
            }
        });

        $this->info('Dependencias de metas producto actualizadas correctamente.');

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
