<?php

namespace App\Services\Analitica;

use App\Services\Intelligence\ReporteSectorial\LectorPasiva;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lee el informe mensual de ejecución presupuestal de gastos de Hacienda.
 * Si encuentra encabezados, usa esos nombres. Si el archivo es la grilla
 * posicional de load_pasiva.py, salta 7 filas y toma las columnas 0, 1, 11, 12, 14, 16 y 18.
 */
class LectorPasivaHacienda
{
    public function __construct(private readonly ClasificadorFuentePasiva $clasificador) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function leer(string $ruta, string $extension): array
    {
        $filas = $this->filas($ruta, strtolower($extension));
        $mapeadas = $this->mapear($filas);

        if ($mapeadas === []) {
            throw ValidationException::withMessages([
                'archivo' => 'No se encontraron rubros en la pasiva. Se esperaba la identificación presupuestal y las columnas de definitiva, certificados, compromisos, obligaciones y pagos.',
            ]);
        }

        return $mapeadas;
    }

    public function corteDetectado(string $ruta, string $extension): ?string
    {
        foreach ($this->filas($ruta, strtolower($extension)) as $fila) {
            $texto = implode(' ', array_map(fn (mixed $valor): string => trim((string) $valor), $fila));

            if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $texto, $partes)) {
                return $partes[3].'-'.$partes[2].'-'.$partes[1];
            }
        }

        return null;
    }

    /**
     * @param  list<list<mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function mapear(array $filas): array
    {
        $encabezado = $this->indiceEncabezado($filas);
        $registros = [];

        if ($encabezado !== null) {
            $mapa = $encabezado['mapa'];

            foreach (array_slice($filas, $encabezado['indice'] + 1) as $fila) {
                $registros[] = [
                    'identificacion' => trim((string) ($fila[$mapa['identificacion']] ?? '')),
                    'concepto' => trim((string) ($fila[$mapa['concepto']] ?? '')),
                    'definitiva' => $fila[$mapa['definitiva']] ?? 0,
                    'certificado' => $fila[$mapa['certificado']] ?? 0,
                    'comprometido' => $fila[$mapa['comprometido']] ?? 0,
                    'obligado' => $fila[$mapa['obligado']] ?? 0,
                    'pagado' => $fila[$mapa['pagado']] ?? 0,
                ];
            }
        } else {
            foreach (array_slice($filas, 7) as $fila) {
                $registros[] = [
                    'identificacion' => trim((string) ($fila[0] ?? '')),
                    'concepto' => trim((string) ($fila[1] ?? '')),
                    'definitiva' => $fila[11] ?? 0,
                    'certificado' => $fila[12] ?? 0,
                    'comprometido' => $fila[14] ?? 0,
                    'obligado' => $fila[16] ?? 0,
                    'pagado' => $fila[18] ?? 0,
                ];
            }
        }

        return $this->clasificar($registros);
    }

    /**
     * @param  list<array<string, mixed>>  $registros
     * @return list<array<string, mixed>>
     */
    private function clasificar(array $registros): array
    {
        $sectores = [];
        $bpines = [];
        $filas = [];

        foreach ($registros as $registro) {
            $identificacion = (string) $registro['identificacion'];
            $concepto = (string) $registro['concepto'];

            if ($identificacion === '') {
                continue;
            }

            $partes = array_map(trim(...), explode(' - ', $identificacion));
            $sector = count($partes) === 2 ? $this->codigoSector($partes[1]) : null;

            if ($sector !== null && str_starts_with(mb_strtoupper($concepto), 'SECTOR:')) {
                $nombre = trim(mb_substr($concepto, mb_strlen('SECTOR:')));
                $sectores[$sector] = $this->capitalizar($nombre);
            }

            if (count($partes) === 2 && ($bpin = $this->bpin($concepto)) !== null) {
                $bpines[$partes[0]."\0".$partes[1]] = $bpin;
            }

            if (count($partes) !== 3) {
                continue;
            }

            [$unidad, $rubro, $fuenteCodigo] = $partes;
            $inversion = str_starts_with($rubro, '2.3.');
            $codigoSector = $inversion ? explode('.', $rubro)[2] ?? null : null;
            $bpin = $inversion ? $this->bpinDelRubro($bpines, $unidad, $rubro) : null;

            $filas[] = [
                'unidad' => $unidad,
                'rubro' => $rubro,
                'concepto' => $concepto,
                'fuente_codigo' => $fuenteCodigo,
                'fuente' => $this->clasificador->etiqueta($fuenteCodigo),
                'grupo_fuente' => $this->clasificador->grupo($fuenteCodigo),
                'sector' => $inversion
                    ? $codigoSector.' — '.($sectores[$codigoSector] ?? '?')
                    : 'Funcionamiento / deuda (no inversión)',
                'bpin' => $bpin ?? ($inversion ? 'Sin BPIN' : 'No aplica'),
                'es_inversion' => $inversion ? 'Inversión' : 'Funcionamiento / deuda',
                'definitiva' => LectorPasiva::numero($registro['definitiva']),
                'certificado' => LectorPasiva::numero($registro['certificado']),
                'comprometido' => LectorPasiva::numero($registro['comprometido']),
                'obligado' => LectorPasiva::numero($registro['obligado']),
                'pagado' => LectorPasiva::numero($registro['pagado']),
            ];
        }

        return $filas;
    }

    /**
     * @param  array<string, string>  $bpines
     */
    private function bpinDelRubro(array $bpines, string $unidad, string $rubro): ?string
    {
        $candidatos = [];

        foreach ($bpines as $clave => $etiqueta) {
            [$unidadClave, $rubroClave] = explode("\0", $clave, 2);

            if ($unidadClave === $unidad && str_starts_with($rubro, $rubroClave.'.')) {
                $candidatos[$rubroClave] = $etiqueta;
            }
        }

        if ($candidatos === []) {
            return null;
        }

        uksort($candidatos, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $candidatos[array_key_first($candidatos)];
    }

    private function codigoSector(string $rubro): ?string
    {
        if (preg_match('/^2\.3\.(\d+)$/', $rubro, $partes) !== 1) {
            return null;
        }

        return $partes[1];
    }

    private function bpin(string $concepto): ?string
    {
        if (preg_match('/^(?:B*PIN\s*)?(\d{4}[0-9A-Z]\d{8,})\s*\.?\s*(.*)/iu', $concepto, $partes) !== 1) {
            return null;
        }

        $resto = trim($partes[2]);

        return 'BPIN '.$partes[1].' — '.$this->capitalizar($resto);
    }

    private function capitalizar(string $texto): string
    {
        $texto = trim($texto);

        if ($texto === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($texto, 0, 1)).mb_strtolower(mb_substr($texto, 1));
    }

    /**
     * @param  list<list<mixed>>  $filas
     * @return array{indice: int, mapa: array<string, int>}|null
     */
    private function indiceEncabezado(array $filas): ?array
    {
        $anterior = null;

        foreach ($filas as $indice => $fila) {
            $normalizados = array_map(fn (mixed $valor): string => $this->normalizar((string) $valor), $fila);

            if ($anterior !== null) {
                foreach ($normalizados as $columna => $texto) {
                    if ($texto === '') {
                        $normalizados[$columna] = $anterior[$columna] ?? '';
                    }
                }
            }

            $mapa = [
                'identificacion' => $this->buscar($normalizados, ['IDENTIFICACION PRESUPUESTAL', 'IDENTIFICACION']),
                'concepto' => $this->buscar($normalizados, ['CONCEPTO']),
                'definitiva' => $this->buscar($normalizados, ['DEFINITIVA', 'APROPIACION DEFINITIVA']),
                'certificado' => $this->buscar($normalizados, ['CERTIFICADOS', 'CERTIFICADO']),
                'comprometido' => $this->buscar($normalizados, ['COMPROMISOS', 'COMPROMETIDO']),
                'obligado' => $this->buscar($normalizados, ['OBLIGACIONES', 'OBLIGADO']),
                'pagado' => $this->buscar($normalizados, ['PAGOS', 'PAGADO']),
            ];

            if (! in_array(null, $mapa, true)) {
                return ['indice' => $indice, 'mapa' => $mapa];
            }

            $anterior = $normalizados;
        }

        return null;
    }

    /**
     * @param  list<string>  $normalizados
     * @param  list<string>  $alias
     */
    private function buscar(array $normalizados, array $alias): ?int
    {
        foreach ($alias as $nombre) {
            $indice = array_search($this->normalizar($nombre), $normalizados, true);

            if ($indice !== false) {
                return (int) $indice;
            }
        }

        return null;
    }

    private function normalizar(string $texto): string
    {
        return (string) Str::of(Str::ascii($texto))->upper()->replaceMatches('/\s+/', ' ')->trim();
    }

    /**
     * @return list<list<mixed>>
     */
    private function filas(string $ruta, string $extension): array
    {
        $reader = match ($extension) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader($this->opcionesCsv($this->delimitador($ruta))),
            default => throw ValidationException::withMessages([
                'archivo' => 'Formato no admitido. Use la pasiva de Hacienda en .xlsx o .csv.',
            ]),
        };

        $reader->open($ruta);
        $filas = [];

        try {
            foreach ($reader->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $row) {
                    $filas[] = array_map(
                        fn (mixed $valor): mixed => is_object($valor) && method_exists($valor, 'getValue') ? $valor->getValue() : $valor,
                        $row->toArray(),
                    );
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $filas;
    }

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
