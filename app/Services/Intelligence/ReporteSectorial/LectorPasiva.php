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
 * Lee la pasiva del PCT (InfMesPptoCDP o Ejecución por periodo) en .xlsx o .csv con openspout.
 * El archivo por periodo trae tres filas de encabezado: el grupo (APROPIACION, CERTIFICADOS…),
 * la fila con IDENTIFICACIÓN PRESUPUESTAL / INICIAL / DEFINITIVA, y Acumulado / Periodo.
 * De cada grupo se toma Acumulado. Si falta una columna de valor esperada, la carga se rechaza.
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

        $crudas = [];

        try {
            foreach ($reader->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $row) {
                    $crudas[] = array_map(
                        fn (mixed $valor): mixed => $valor instanceof Cell ? $valor->getValue() : $valor,
                        $row->toArray(),
                    );
                }

                break;
            }
        } finally {
            $reader->close();
        }

        $encabezado = $this->resolverEncabezado($crudas);
        $filas = [];

        foreach ($crudas as $indice => $valores) {
            if ($indice < $encabezado['desde']) {
                continue;
            }

            $registro = [];

            foreach ($encabezado['mapa'] as $campo => $columna) {
                $registro[$campo] = $valores[$columna] ?? null;
            }

            if (trim((string) ($registro['identificacion'] ?? '')) === '') {
                continue;
            }

            $filas[] = ['fila' => $indice + 1, 'valores' => $registro];
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
     * @param  list<list<mixed>>  $filas
     * @return array{mapa: array<string, int>, desde: int}
     */
    private function resolverEncabezado(array $filas): array
    {
        $limite = min(count($filas), (int) config('reporte_sectorial.pasiva.filas_busqueda_encabezado', 20));

        for ($indice = 0; $indice < $limite; $indice++) {
            if ($this->indiceEtiqueta($filas[$indice], (array) config('reporte_sectorial.pasiva.columnas.identificacion')) === null) {
                continue;
            }

            $anterior = $indice > 0 ? $filas[$indice - 1] : [];
            $detalle = $filas[$indice + 1] ?? [];
            $subencabezado = $this->esSubencabezado($detalle);
            $mapa = $this->construirMapa($anterior, $filas[$indice], $subencabezado ? $detalle : []);
            $this->exigirColumnas($mapa);

            return [
                'mapa' => $mapa,
                'desde' => $indice + ($subencabezado ? 2 : 1),
            ];
        }

        throw ValidationException::withMessages([
            'archivo' => 'No se encontró la fila de encabezados de la pasiva. Se esperaba una columna "IDENTIFICACIÓN PRESUPUESTAL" y las columnas de valores (INICIAL, DEFINITIVA, CERTIFICADOS, COMPROMISOS, OBLIGACIONES, PAGOS).',
        ]);
    }

    /**
     * @param  list<mixed>  $grupo
     * @param  list<mixed>  $principal
     * @param  list<mixed>  $detalle
     * @return array<string, int>
     */
    private function construirMapa(array $grupo, array $principal, array $detalle): array
    {
        $mapa = [];
        $hayDetalle = $detalle !== [];

        foreach (config('reporte_sectorial.pasiva.columnas') as $campo => $alias) {
            $columna = $this->columna((array) $alias, $grupo, $principal, $detalle, $hayDetalle);

            if ($columna !== null) {
                $mapa[$campo] = $columna;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $alias
     * @param  list<mixed>  $grupo
     * @param  list<mixed>  $principal
     * @param  list<mixed>  $detalle
     */
    private function columna(array $alias, array $grupo, array $principal, array $detalle, bool $hayDetalle): ?int
    {
        $enPrincipal = $this->indiceEtiqueta($principal, $alias);
        $enGrupo = $this->indiceEtiqueta($grupo, $alias);

        if ($hayDetalle) {
            foreach ([[$enPrincipal, $principal, [$grupo]], [$enGrupo, $grupo, [$principal]]] as [$inicio, $fila, $cortes]) {
                if ($inicio === null) {
                    continue;
                }

                $fin = $this->finDeSpan($fila, $inicio, $cortes);
                $acumulado = $this->indiceEtiquetaEnRango($detalle, (array) config('reporte_sectorial.pasiva.acumulado'), $inicio, $fin);

                if ($acumulado !== null) {
                    return $acumulado;
                }
            }

            if ($enPrincipal !== null && $this->texto($detalle[$enPrincipal] ?? null) === '') {
                return $enPrincipal;
            }
        }

        return $enPrincipal;
    }

    /**
     * @param  array<string, int>  $mapa
     */
    private function exigirColumnas(array $mapa): void
    {
        $faltan = [];

        foreach ((array) config('reporte_sectorial.pasiva.obligatorias') as $campo) {
            if (! array_key_exists($campo, $mapa)) {
                $faltan[] = (string) config('reporte_sectorial.pasiva.etiquetas.'.$campo, $campo);
            }
        }

        if ($faltan === []) {
            return;
        }

        throw ValidationException::withMessages([
            'archivo' => 'La pasiva no trae las columnas de valores esperadas: '.implode(', ', $faltan).'. En el archivo del PCT por periodo se toma la columna Acumulado de cada grupo (certificados, compromisos, obligaciones y pagos), no la de Periodo.',
        ]);
    }

    /**
     * @param  list<mixed>  $fila
     */
    private function esSubencabezado(array $fila): bool
    {
        return $this->indiceEtiqueta($fila, (array) config('reporte_sectorial.pasiva.acumulado')) !== null
            && $this->indiceEtiqueta($fila, (array) config('reporte_sectorial.pasiva.periodo')) !== null;
    }

    /**
     * @param  list<mixed>  $fila
     * @param  list<list<mixed>>  $cortes
     */
    private function finDeSpan(array $fila, int $inicio, array $cortes): int
    {
        $limite = count($fila);

        foreach ($cortes as $corte) {
            $limite = max($limite, count($corte));
        }

        for ($columna = $inicio + 1; $columna < $limite; $columna++) {
            if ($this->texto($fila[$columna] ?? null) !== '') {
                return $columna - 1;
            }

            foreach ($cortes as $corte) {
                if ($this->texto($corte[$columna] ?? null) !== '') {
                    return $columna - 1;
                }
            }
        }

        return max($inicio, $limite - 1);
    }

    /**
     * @param  list<mixed>  $fila
     * @param  list<string>  $alias
     */
    private function indiceEtiqueta(array $fila, array $alias): ?int
    {
        return $this->indiceEtiquetaEnRango($fila, $alias, 0, max(0, count($fila) - 1));
    }

    /**
     * @param  list<mixed>  $fila
     * @param  list<string>  $alias
     */
    private function indiceEtiquetaEnRango(array $fila, array $alias, int $desde, int $hasta): ?int
    {
        $buscadas = array_map(fn (string $nombre): string => $this->normalizar($nombre), $alias);

        for ($columna = $desde; $columna <= $hasta; $columna++) {
            if (in_array($this->texto($fila[$columna] ?? null), $buscadas, true)) {
                return $columna;
            }
        }

        return null;
    }

    private function texto(mixed $valor): string
    {
        return $this->normalizar((string) $valor);
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
