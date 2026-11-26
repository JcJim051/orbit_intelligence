<?php

namespace App\Services\Suelos;

use App\Services\Intelligence\CatalogWorkbook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogoExcel
{
    public function __construct(private CatalogWorkbook $libro) {}

    /**
     * @param  class-string<Model>  $modelo
     * @param  list<array{name: string, label: string, tipo: string, excel?: string, modelo?: class-string<Model>}>  $columnas
     */
    public function exportar(string $modelo, array $columnas, string $ruta): void
    {
        $encabezados = array_map(fn (array $columna): string => $columna['excel'] ?? $columna['name'], $columnas);
        $filas = $modelo::query()->orderBy('codigo')->get()->map(function (Model $registro) use ($columnas): array {
            return array_map(function (array $columna) use ($registro): mixed {
                if ($columna['tipo'] === 'relacion') {
                    $relacion = $registro->getRelationValue($this->nombreRelacion($columna['name']));

                    return $relacion?->codigo;
                }

                $valor = $registro->getAttribute($columna['name']);

                if ($valor instanceof \BackedEnum) {
                    return $valor->value;
                }

                if (is_bool($valor)) {
                    return $valor ? 1 : 0;
                }

                return $valor;
            }, $columnas);
        })->all();

        $this->libro->write($ruta, 'Datos', $encabezados, $filas);
    }

    /**
     * @param  list<array{name: string, label: string, tipo: string, excel?: string, modelo?: class-string<Model>}>  $columnas
     */
    public function plantilla(array $columnas, string $ruta): void
    {
        $encabezados = array_map(fn (array $columna): string => $columna['excel'] ?? $columna['name'], $columnas);
        $this->libro->write($ruta, 'Datos', $encabezados, [], [
            'Use el código de la entidad relacionada, con el formato código — nombre en pantalla, pero en Excel solo el código.',
            'Los campos sí/no se escriben 1 o 0. Si una fila falla, no se importa ninguna.',
        ]);
    }

    /**
     * @param  class-string<Model>  $modelo
     * @param  list<array{name: string, label: string, tipo: string, excel?: string, modelo?: class-string<Model>, requerido?: bool}>  $columnas
     */
    public function importar(string $modelo, array $columnas, string $ruta): void
    {
        $hoja = $this->libro->readTable($ruta);
        $encabezados = array_map(fn (array $columna): string => $columna['excel'] ?? $columna['name'], $columnas);

        if ($hoja['headers'] !== $encabezados) {
            throw ValidationException::withMessages([
                'archivo' => 'La primera fila debe contener exactamente estas columnas: '.implode(', ', $encabezados).'.',
            ]);
        }

        $preparadas = [];
        $errores = [];

        foreach ($hoja['rows'] as $fila) {
            if (collect($fila['values'])->every(fn (mixed $valor): bool => trim((string) $valor) === '')) {
                continue;
            }

            $atributos = [];
            $error = null;

            foreach ($columnas as $columna) {
                $encabezado = $columna['excel'] ?? $columna['name'];
                $texto = trim((string) ($fila['values'][$encabezado] ?? ''));

                if ($texto === '') {
                    $atributos[$columna['name']] = $columna['tipo'] === 'bool' ? false : null;

                    continue;
                }

                if ($columna['tipo'] === 'relacion') {
                    $relacionado = ($columna['modelo'])::query()->where('codigo', $texto)->first();

                    if ($relacionado === null) {
                        $error = 'Fila '.$fila['line'].': no existe '.$encabezado.' con código '.$texto.'.';

                        break;
                    }

                    $atributos[$columna['name']] = $relacionado->getKey();

                    continue;
                }

                $atributos[$columna['name']] = match ($columna['tipo']) {
                    'bool' => in_array(mb_strtolower($texto), ['1', 'true', 'si', 'sí'], true),
                    'numero' => is_numeric($texto) ? $texto + 0 : $texto,
                    default => $texto,
                };
            }

            if ($error !== null) {
                $errores[] = $error;

                continue;
            }

            if (trim((string) ($atributos['codigo'] ?? '')) === '' || trim((string) ($atributos['nombre'] ?? '')) === '') {
                $errores[] = 'Fila '.$fila['line'].': código y nombre son obligatorios.';

                continue;
            }

            $preparadas[] = $atributos;
        }

        if ($errores !== []) {
            throw ValidationException::withMessages(['archivo' => implode(' ', $errores)]);
        }

        DB::transaction(function () use ($modelo, $preparadas): void {
            foreach ($preparadas as $atributos) {
                $modelo::query()->updateOrCreate(
                    ['codigo' => $atributos['codigo']],
                    $atributos,
                );
            }
        });
    }

    private function nombreRelacion(string $columna): string
    {
        return match ($columna) {
            'unidad_id' => 'unidad',
            'unidad_base_id' => 'unidadBase',
            default => str($columna)->beforeLast('_id')->camel()->toString(),
        };
    }
}
