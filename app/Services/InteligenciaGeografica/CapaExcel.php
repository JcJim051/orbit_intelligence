<?php

namespace App\Services\InteligenciaGeografica;

use App\Enums\FrecuenciaActualizacionCapa;
use App\Enums\TipoAccesoCapa;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\Fuente;
use App\Services\Intelligence\CatalogWorkbook;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CapaExcel
{
    /**
     * @var list<string>
     */
    public const COLUMNAS = [
        'codigo',
        'nombre',
        'fuente',
        'tipo_acceso',
        'frecuencia_actualizacion',
        'ttl_horas',
        'licencia',
        'cita_fuente',
        'pregunta',
        'url_servicio',
        'activa',
        'observacion',
    ];

    public function __construct(private CatalogWorkbook $libro) {}

    public function exportar(string $ruta): void
    {
        $filas = Capa::query()->with('fuente')->orderBy('codigo')->get()->map(fn (Capa $capa): array => [
            $capa->codigo,
            $capa->nombre,
            $capa->fuente?->codigo,
            $capa->tipo_acceso->value,
            $capa->frecuencia_actualizacion->value,
            $capa->ttl_horas,
            $capa->licencia,
            $capa->cita_fuente,
            $capa->pregunta,
            $capa->url_servicio,
            $capa->activa ? 1 : 0,
            $capa->observacion,
        ])->all();

        $this->libro->write($ruta, 'Capas', self::COLUMNAS, $filas);
    }

    public function plantilla(string $ruta): void
    {
        $this->libro->write($ruta, 'Capas', self::COLUMNAS, [], [
            'La columna fuente usa el código de la entidad, por ejemplo IGAC.',
            'tipo_acceso: rest, wfs, descarga, cargue_propio.',
            'frecuencia_actualizacion: diaria, semanal, mensual, por_version, tiempo_real.',
            'activa: 1 o 0. Una fila en blanco se ignora. Si alguna fila falla, no se importa ninguna.',
        ]);
    }

    public function importar(string $ruta): void
    {
        $hoja = $this->libro->readTable($ruta);

        if ($hoja['headers'] !== self::COLUMNAS) {
            throw ValidationException::withMessages([
                'archivo' => 'La primera fila debe contener exactamente estas columnas: '.implode(', ', self::COLUMNAS).'.',
            ]);
        }

        $preparadas = [];
        $errores = [];
        $vistas = [];

        foreach ($hoja['rows'] as $fila) {
            $valores = $fila['values'];

            if (collect($valores)->every(fn (mixed $valor): bool => trim((string) $valor) === '')) {
                continue;
            }

            $codigo = trim((string) ($valores['codigo'] ?? ''));

            if ($codigo === '' || isset($vistas[$codigo])) {
                $errores[] = 'Fila '.$fila['line'].': el código es obligatorio y no puede repetirse.';

                continue;
            }

            $vistas[$codigo] = true;
            $fuente = Fuente::query()->where('codigo', trim((string) ($valores['fuente'] ?? '')))->first();
            $tipo = TipoAccesoCapa::tryFrom(trim((string) ($valores['tipo_acceso'] ?? '')));
            $frecuencia = FrecuenciaActualizacionCapa::tryFrom(trim((string) ($valores['frecuencia_actualizacion'] ?? '')));
            $ttl = trim((string) ($valores['ttl_horas'] ?? ''));

            if ($fuente === null || $tipo === null || $frecuencia === null || ! ctype_digit($ttl)) {
                $errores[] = 'Fila '.$fila['line'].': revise la fuente, el tipo de acceso, la frecuencia y el TTL en horas.';

                continue;
            }

            if (trim((string) ($valores['nombre'] ?? '')) === '' || trim((string) ($valores['cita_fuente'] ?? '')) === '' || trim((string) ($valores['pregunta'] ?? '')) === '') {
                $errores[] = 'Fila '.$fila['line'].': nombre, cita de la fuente y pregunta son obligatorios.';

                continue;
            }

            $preparadas[] = [
                'codigo' => $codigo,
                'nombre' => trim((string) $valores['nombre']),
                'fuente_id' => $fuente->id,
                'tipo_acceso' => $tipo,
                'frecuencia_actualizacion' => $frecuencia,
                'ttl_horas' => (int) $ttl,
                'licencia' => $this->nulo($valores['licencia'] ?? null),
                'cita_fuente' => trim((string) $valores['cita_fuente']),
                'pregunta' => trim((string) $valores['pregunta']),
                'url_servicio' => $this->nulo($valores['url_servicio'] ?? null),
                'activa' => in_array((string) ($valores['activa'] ?? '1'), ['1', 'true', 'si', 'sí'], true),
                'observacion' => $this->nulo($valores['observacion'] ?? null),
                'estado_frescura' => 'posiblemente_desactualizada',
            ];
        }

        if ($errores !== []) {
            throw ValidationException::withMessages(['archivo' => implode(' ', $errores)]);
        }

        DB::transaction(function () use ($preparadas): void {
            foreach ($preparadas as $atributos) {
                $existente = Capa::query()->where('codigo', $atributos['codigo'])->first();

                if ($existente instanceof Capa) {
                    unset($atributos['estado_frescura']);
                    $existente->update($atributos);

                    continue;
                }

                Capa::query()->create($atributos);
            }
        });
    }

    private function nulo(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
