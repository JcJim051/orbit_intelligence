<?php

namespace App\Services\Intelligence\ReporteSectorial;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lee la pasiva del PCT (InfMesPptoCDP) en .xlsx o .csv con openspout.
 * Detecta la fila de encabezados y traduce las columnas según config('reporte_sectorial.pasiva.columnas').
 */
class LectorPasiva
{
    /**
     * @return list<array{fila: int, valores: array<string, mixed>}>
     */
    public function leer(string $ruta, string $extension): array
    {
        $reader = $this->reader($ruta, strtolower($extension));
        $reader->open($ruta);

        $mapa = null;
        $filas = [];
        $numero = 0;
        $limiteBusqueda = (int) config('reporte_sectorial.pasiva.filas_busqueda_encabezado', 20);

        try {
            foreach ($reader->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $row) {
                    $numero++;
                    $valores = array_map(fn (mixed $valor): mixed => $valor instanceof Cell ? $valor->getValue() : $valor, $row->toArray());

                    if ($mapa === null) {
                        $mapa = $this->mapaEncabezados($valores);

                        if ($mapa === null && $numero >= $limiteBusqueda) {
                            break 2;
                        }

                        continue;
                    }

                    $registro = [];

                    foreach ($mapa as $campo => $indice) {
                        $registro[$campo] = $valores[$indice] ?? null;
                    }

                    if (trim((string) ($registro['identificacion'] ?? '')) === '') {
                        continue;
                    }

                    $filas[] = ['fila' => $numero, 'valores' => $registro];
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($mapa === null) {
            throw ValidationException::withMessages([
                'archivo' => 'No se encontró la fila de encabezados de la pasiva. Se esperaba una columna "IDENTIFICACIÓN PRESUPUESTAL" y las columnas de valores (INICIAL, DEFINITIVA, CERTIFICADOS, COMPROMISOS, OBLIGACIONES, PAGOS).',
            ]);
        }

        return $filas;
    }

    public static function numero(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        if (is_int($valor) || is_float($valor)) {
            return round((float) $valor, 2);
        }

        $texto = preg_replace('/[^\d,.\-]/', '', (string) $valor) ?? '';

        if ($texto === '' || $texto === '-') {
            return 0.0;
        }

        $ultimaComa = strrpos($texto, ',');
        $ultimoPunto = strrpos($texto, '.');

        if ($ultimaComa !== false && ($ultimoPunto === false || $ultimaComa > $ultimoPunto)) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        } else {
            $texto = str_replace(',', '', $texto);
        }

        return round((float) $texto, 2);
    }

    /**
     * @param  list<mixed>  $valores
     * @return array<string, int>|null
     */
    private function mapaEncabezados(array $valores): ?array
    {
        $normalizados = array_map(fn (mixed $valor): string => $this->normalizar((string) $valor), $valores);
        $mapa = [];

        foreach (config('reporte_sectorial.pasiva.columnas') as $campo => $alias) {
            foreach ((array) $alias as $nombre) {
                $indice = array_search($this->normalizar($nombre), $normalizados, true);

                if ($indice !== false) {
                    $mapa[$campo] = $indice;

                    break;
                }
            }
        }

        foreach (config('reporte_sectorial.pasiva.obligatorias') as $obligatoria) {
            if (! array_key_exists($obligatoria, $mapa)) {
                return null;
            }
        }

        return $mapa;
    }

    private function normalizar(string $texto): string
    {
        return (string) Str::of(Str::ascii($texto))->upper()->replaceMatches('/\s+/', ' ')->trim();
    }

    private function reader(string $ruta, string $extension): ReaderInterface
    {
        if ($extension === 'xlsx') {
            return new XlsxReader;
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            return new CsvReader($this->opcionesCsv($this->delimitador($ruta)));
        }

        throw ValidationException::withMessages([
            'archivo' => 'Formato no admitido. Guarde la pasiva del PCT como .xlsx o .csv.',
        ]);
    }

    /**
     * Compatible con openspout 4 (opciones mutables) y 5 (opciones readonly por constructor).
     */
    private function opcionesCsv(string $delimitador): CsvOptions
    {
        if ((new \ReflectionClass(CsvOptions::class))->getConstructor() !== null) {
            return new CsvOptions(FIELD_DELIMITER: $delimitador);
        }

        $opciones = new CsvOptions;
        $opciones->FIELD_DELIMITER = $delimitador;

        return $opciones;
    }

    private function delimitador(string $ruta): string
    {
        $muestra = (string) file_get_contents($ruta, false, null, 0, 8192);
        $conteos = [';' => substr_count($muestra, ';'), ',' => substr_count($muestra, ','), "\t" => substr_count($muestra, "\t")];
        arsort($conteos);

        return (string) array_key_first($conteos);
    }
}
