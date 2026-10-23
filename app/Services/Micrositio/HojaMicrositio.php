<?php

namespace App\Services\Micrositio;

use App\Models\MicrositioTema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

class HojaMicrositio
{
    public function __construct(private readonly PublicadorMicrositio $publicador) {}

    public function exportar(string $seccion, string $ruta): void
    {
        $definicion = $this->definicion($seccion);
        $filas = [];

        foreach ($this->publicador->registrosBorrador($seccion) as $registro) {
            $linea = [(string) $registro['id']];

            foreach (array_keys($definicion['columnas']) as $columna) {
                $linea[] = $this->celda($registro['atributos'][$columna] ?? null);
            }

            $filas[] = $linea;
        }

        $this->escribir($ruta, array_merge(['id'], array_values($definicion['columnas'])), $filas, $definicion['instrucciones']);
    }

    public function plantilla(string $seccion, string $ruta): void
    {
        $definicion = $this->definicion($seccion);
        $ejemplo = array_merge([''], $definicion['ejemplo']);

        $this->escribir(
            $ruta,
            array_merge(['id'], array_values($definicion['columnas'])),
            [$ejemplo],
            $definicion['instrucciones'],
        );
    }

    public function importar(string $seccion, string $ruta): int
    {
        $definicion = $this->definicion($seccion);
        $reader = new Reader;
        $reader->open($ruta);
        $encabezados = null;
        $filas = [];
        $errores = [];
        $numero = 1;

        try {
            foreach ($reader->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $row) {
                    $valores = array_map(fn (mixed $valor): string => trim((string) $valor), $row->toArray());

                    if ($encabezados === null) {
                        $encabezados = $valores;

                        continue;
                    }

                    $numero++;

                    if (count(array_filter($valores, fn (string $valor): bool => $valor !== '')) === 0) {
                        continue;
                    }

                    $registro = [];

                    foreach ($encabezados as $indice => $encabezado) {
                        $registro[$this->normalizar($encabezado)] = $valores[$indice] ?? '';
                    }

                    if (($registro[$definicion['marca_ejemplo']] ?? '') === 'EJEMPLO') {
                        continue;
                    }

                    try {
                        $filas[] = $definicion['interpretar']($registro);
                    } catch (ValidationException $exception) {
                        $errores[] = 'Fila '.$numero.': '.collect($exception->errors())->flatten()->first();
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($errores !== []) {
            throw ValidationException::withMessages(['archivo' => $errores]);
        }

        $modelo = $this->publicador->modelo($seccion);

        DB::transaction(function () use ($filas, $modelo): void {
            $modelo::query()->delete();

            foreach ($filas as $fila) {
                $modelo::query()->create($fila);
            }
        });

        return count($filas);
    }

    /**
     * @param  list<string>  $encabezados
     * @param  list<list<mixed>>  $filas
     * @param  list<string>  $instrucciones
     */
    private function escribir(string $ruta, array $encabezados, array $filas, array $instrucciones): void
    {
        $writer = new Writer;
        $writer->openToFile($ruta);
        $writer->getCurrentSheet()->setName('Datos');
        $writer->addRow(Row::fromValues($encabezados));

        foreach ($filas as $fila) {
            $writer->addRow(Row::fromValues(array_values($fila)));
        }

        if ($instrucciones !== []) {
            $writer->addNewSheetAndMakeItCurrent()->setName('Instrucciones');

            foreach ($instrucciones as $linea) {
                $writer->addRow(Row::fromValues([$linea]));
            }
        }

        $writer->close();
    }

    /**
     * @return array{columnas: array<string, string>, ejemplo: list<mixed>, instrucciones: list<string>, marca_ejemplo: string, interpretar: callable}
     */
    private function definicion(string $seccion): array
    {
        $instrucciones = [
            'La primera fila es el encabezado. No la modifique.',
            'La fila de ejemplo trae EJEMPLO en la columna marcada y se ignora al importar.',
            'La importación reemplaza todo el borrador de la sección. El id del archivo se ignora.',
            'Los cambios quedan en borrador hasta que publique la sección en el CMS.',
            'No invente cifras. Si un dato es ilustrativo, marque es_ejemplo en 1.',
        ];

        return match ($seccion) {
            'cifras' => [
                'columnas' => [
                    'valor' => 'valor',
                    'descripcion' => 'descripcion',
                    'fuente' => 'fuente',
                    'fecha_corte' => 'fecha_corte',
                    'es_ejemplo' => 'es_ejemplo',
                    'orden' => 'orden',
                ],
                'ejemplo' => ['EJEMPLO', 'Descripción de ejemplo', 'Fuente de ejemplo', '2026-01-01', 1, 0],
                'instrucciones' => $instrucciones,
                'marca_ejemplo' => 'valor',
                'interpretar' => fn (array $fila): array => [
                    'valor' => $this->requerido($fila, 'valor'),
                    'descripcion' => $this->requerido($fila, 'descripcion'),
                    'fuente' => $this->requerido($fila, 'fuente'),
                    'fecha_corte' => $this->fecha($this->requerido($fila, 'fecha_corte')),
                    'es_ejemplo' => $this->booleano($fila['es_ejemplo'] ?? '0'),
                    'orden' => (int) ($fila['orden'] ?? 0),
                ],
            ],
            'temas' => [
                'columnas' => ['slug' => 'slug', 'nombre' => 'nombre', 'orden' => 'orden'],
                'ejemplo' => ['ejemplo', 'EJEMPLO', 0],
                'instrucciones' => $instrucciones,
                'marca_ejemplo' => 'nombre',
                'interpretar' => fn (array $fila): array => [
                    'slug' => $this->requerido($fila, 'slug'),
                    'nombre' => $this->requerido($fila, 'nombre'),
                    'orden' => (int) ($fila['orden'] ?? 0),
                ],
            ],
            'indicadores' => [
                'columnas' => [
                    'codigo' => 'codigo',
                    'nombre' => 'nombre',
                    'tema_slug' => 'tema_slug',
                    'dashboard_uuid' => 'dashboard_uuid',
                    'descripcion' => 'descripcion',
                    'es_publico' => 'es_publico',
                    'es_ejemplo' => 'es_ejemplo',
                    'conjunto_datos' => 'conjunto_datos',
                    'orden' => 'orden',
                ],
                'ejemplo' => ['EJEMPLO', 'Nombre de ejemplo', 'finanzas', '', 'Descripción de ejemplo', 0, 1, '', 0],
                'instrucciones' => array_merge($instrucciones, [
                    'tema_slug debe existir en el borrador de temas.',
                    'dashboard_uuid es el UUID del tablero embebido en Superset. Vacío si todavía no existe.',
                    'conjunto_datos admite vacío o pasiva.',
                ]),
                'marca_ejemplo' => 'codigo',
                'interpretar' => function (array $fila): array {
                    $slug = $this->requerido($fila, 'tema_slug');
                    $tema = MicrositioTema::query()->where('slug', $slug)->first();

                    if ($tema === null) {
                        throw ValidationException::withMessages(['tema_slug' => 'No existe el tema '.$slug.'.']);
                    }

                    $uuid = trim((string) ($fila['dashboard_uuid'] ?? ''));

                    if ($uuid !== '' && ! preg_match('/^[0-9a-fA-F-]{36}$/', $uuid)) {
                        throw ValidationException::withMessages(['dashboard_uuid' => 'El UUID del tablero no tiene formato válido.']);
                    }

                    $conjunto = trim((string) ($fila['conjunto_datos'] ?? ''));

                    if ($conjunto !== '' && $conjunto !== 'pasiva') {
                        throw ValidationException::withMessages(['conjunto_datos' => 'El conjunto de datos solo puede ser pasiva o vacío.']);
                    }

                    return [
                        'codigo' => $this->requerido($fila, 'codigo'),
                        'nombre' => $this->requerido($fila, 'nombre'),
                        'micrositio_tema_id' => $tema->id,
                        'dashboard_uuid' => $uuid !== '' ? $uuid : null,
                        'descripcion' => $this->requerido($fila, 'descripcion'),
                        'es_publico' => $this->booleano($fila['es_publico'] ?? '0'),
                        'es_ejemplo' => $this->booleano($fila['es_ejemplo'] ?? '0'),
                        'conjunto_datos' => $conjunto !== '' ? $conjunto : null,
                        'orden' => (int) ($fila['orden'] ?? 0),
                    ];
                },
            ],
            'publicaciones' => [
                'columnas' => [
                    'tipo' => 'tipo',
                    'titulo' => 'titulo',
                    'fecha' => 'fecha',
                    'resumen' => 'resumen',
                    'es_ejemplo' => 'es_ejemplo',
                    'orden' => 'orden',
                ],
                'ejemplo' => ['EJEMPLO', 'Título de ejemplo', '2026-01-01', 'Resumen de ejemplo', 1, 0],
                'instrucciones' => array_merge($instrucciones, ['El archivo se carga en el formulario del CMS, no en el Excel.']),
                'marca_ejemplo' => 'tipo',
                'interpretar' => fn (array $fila): array => [
                    'tipo' => $this->requerido($fila, 'tipo'),
                    'titulo' => $this->requerido($fila, 'titulo'),
                    'fecha' => $this->fecha($this->requerido($fila, 'fecha')),
                    'resumen' => $this->requerido($fila, 'resumen'),
                    'es_ejemplo' => $this->booleano($fila['es_ejemplo'] ?? '0'),
                    'orden' => (int) ($fila['orden'] ?? 0),
                ],
            ],
            'menu' => [
                'columnas' => ['texto' => 'texto', 'url' => 'url', 'orden' => 'orden'],
                'ejemplo' => ['EJEMPLO', '/micrositio', 0],
                'instrucciones' => $instrucciones,
                'marca_ejemplo' => 'texto',
                'interpretar' => fn (array $fila): array => [
                    'texto' => $this->requerido($fila, 'texto'),
                    'url' => $this->requerido($fila, 'url'),
                    'orden' => (int) ($fila['orden'] ?? 0),
                ],
            ],
            default => throw ValidationException::withMessages(['seccion' => 'Esta sección no tiene plantilla de Excel.']),
        };
    }

    /**
     * @param  array<string, string>  $fila
     */
    private function requerido(array $fila, string $campo): string
    {
        $valor = trim((string) ($fila[$campo] ?? ''));

        if ($valor === '') {
            throw ValidationException::withMessages([$campo => 'El campo '.$campo.' es obligatorio.']);
        }

        return $valor;
    }

    private function fecha(string $valor): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes)) {
            return $partes[1].'-'.$partes[2].'-'.$partes[3];
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $partes)) {
            return $partes[3].'-'.$partes[2].'-'.$partes[1];
        }

        throw ValidationException::withMessages(['fecha' => 'Use la fecha como AAAA-MM-DD o DD/MM/AAAA.']);
    }

    private function booleano(string $valor): bool
    {
        return in_array(mb_strtolower(trim($valor)), ['1', 'si', 'sí', 'true', 'verdadero'], true);
    }

    private function celda(mixed $valor): mixed
    {
        if (is_bool($valor)) {
            return $valor ? 1 : 0;
        }

        return $valor ?? '';
    }

    private function normalizar(string $texto): string
    {
        return mb_strtolower(trim($texto));
    }
}
