<?php

namespace App\Services\Intelligence\ReporteSectorial;

use App\Models\Actividad;
use App\Models\ActividadProgramacion;
use App\Models\Dependencia;
use App\Models\MetaProducto;
use App\Models\PddPilar;
use App\Models\PddPrograma;
use App\Models\ReporteProyecto;
use App\Models\Seguimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnaliticaMetasProducto
{
    /**
     * @param  array{dependencia: int|null, q: string, estado: string}  $filtros
     * @param  list<int>|null  $dependenciaIdsVisibles
     * @return array{filas: Collection<int, array<string, mixed>>, resumen: array<string, mixed>}
     */
    public function construir(Seguimiento $seguimiento, array $filtros, ?array $dependenciaIdsVisibles = null): array
    {
        $reportes = ReporteProyecto::query()
            ->withoutGlobalScopes()
            ->where('seguimiento_id', $seguimiento->id)
            ->when(is_array($dependenciaIdsVisibles), fn ($query) => $query->whereIn('dependencia_id', $dependenciaIdsVisibles ?: [0]))
            ->when($filtros['dependencia'], fn ($query, $dependenciaId) => $query->where('dependencia_id', $dependenciaId))
            ->with([
                'proyecto:id,bpin,nombre',
                'dependencia:id,codigo,nombre,sigla',
                'avances' => fn ($query) => $query->withoutGlobalScopes(),
                'avances.actividad' => fn ($query) => $query->withoutGlobalScopes(),
                'avances.actividad.metaProducto.dependencia:id,codigo,nombre,sigla',
                'avances.actividad.metaProducto.metaResultado.programa.linea.eje.pilar',
                'avances.actividad.metaProducto.metaResultado.subprograma.programa.linea.eje.pilar',
                'avances.actividad.metaProducto.subprograma.programa.linea.eje.pilar',
                'ejecuciones.fuente:id,codigo,nombre',
                'ejecuciones' => fn ($query) => $query->withoutGlobalScopes(),
                'ejecuciones.actividad' => fn ($query) => $query->withoutGlobalScopes(),
                'ejecuciones.actividad.metaProducto.dependencia:id,codigo,nombre,sigla',
                'ejecuciones.actividad.metaProducto.metaResultado.programa.linea.eje.pilar',
                'ejecuciones.actividad.metaProducto.metaResultado.subprograma.programa.linea.eje.pilar',
                'ejecuciones.actividad.metaProducto.subprograma.programa.linea.eje.pilar',
            ])
            ->get();

        $filas = collect();

        foreach ($reportes as $reporte) {
            $actividades = $reporte->avances
                ->pluck('actividad')
                ->merge($reporte->ejecuciones->pluck('actividad'))
                ->filter(fn (?Actividad $actividad): bool => $actividad?->metaProducto !== null)
                ->unique('id')
                ->values();

            foreach ($actividades as $actividad) {
                $meta = $actividad->metaProducto;
                $llave = $meta->id.'-'.$reporte->dependencia_id;
                $actual = $filas->get($llave) ?? $this->filaBase($meta, $reporte->dependencia);

                $actual['proyectos'][$reporte->proyecto_id] = [
                    'bpin' => $reporte->proyecto->bpin,
                    'nombre' => $reporte->proyecto->nombre,
                ];
                $actual['fuentes'] = array_replace($actual['fuentes'], $reporte->ejecuciones
                    ->where('actividad_id', $actividad->id)
                    ->mapWithKeys(fn ($ejecucion): array => [$ejecucion->fuente_financiacion_id => $ejecucion->fuente?->etiqueta() ?? 'Sin fuente'])
                    ->all());

                $actual['programacion_fisica'] += (float) ($actividad->cantidad_programada ?? 0);
                $actual['avance_fisico'] += (float) ($reporte->avances->firstWhere('actividad_id', $actividad->id)?->cantidad ?? 0);
                $actual['asignado'] += (float) ActividadProgramacion::query()
                    ->withoutGlobalScopes()
                    ->where('actividad_id', $actividad->id)
                    ->where('vigencia', $seguimiento->vigencia)
                    ->sum('valor_asignado');
                $actual['comprometido'] += (float) $reporte->ejecuciones->where('actividad_id', $actividad->id)->sum('comprometido');
                $actual['obligado'] += (float) $reporte->ejecuciones->where('actividad_id', $actividad->id)->sum('obligado');
                $actual['pagado'] += (float) $reporte->ejecuciones->where('actividad_id', $actividad->id)->sum('pagado');
                $actual['observaciones'] = $this->agregarObservaciones($actual['observaciones'], $reporte->avances->firstWhere('actividad_id', $actividad->id)?->descripcion);

                $filas->put($llave, $actual);
            }
        }

        $filas = $filas
            ->map(fn (array $fila): array => $this->calcularIndicadores($fila))
            ->filter(fn (array $fila): bool => $this->pasaFiltros($fila, $filtros))
            ->sortBy([
                fn (array $fila): string => (string) ($fila['pilar_codigo'] ?? 'ZZZ'),
                fn (array $fila): string => (string) $fila['dependencia_nombre'],
                fn (array $fila): string => (string) $fila['meta_codigo'],
            ])
            ->values();

        return [
            'filas' => $filas,
            'resumen' => [
                'metas' => $filas->count(),
                'proyectos' => $filas->flatMap(fn (array $fila): array => array_keys($fila['proyectos']))->unique()->count(),
                'dependencias' => $filas->pluck('dependencia_id')->unique()->count(),
                'asignado' => $filas->sum('asignado'),
                'comprometido' => $filas->sum('comprometido'),
                'obligado' => $filas->sum('obligado'),
                'avance_fisico_promedio' => $filas->avg('porcentaje_fisico') ?? 0,
                'ejecucion_financiera' => $filas->sum('asignado') > 0 ? ($filas->sum('comprometido') / $filas->sum('asignado')) * 100 : 0,
                'sin_avance' => $filas->where('estado_analitico', 'sin_avance')->count(),
                'cumplidas' => $filas->whereIn('estado_analitico', ['cumplida', 'sobrecumplida'])->count(),
            ],
        ];
    }

    private function filaBase(MetaProducto $meta, Dependencia $dependencia): array
    {
        $pilar = $this->pilarDe($meta);
        $programa = $this->programaDe($meta);

        return [
            'meta_id' => $meta->id,
            'meta_codigo' => $meta->codigo,
            'meta_nombre' => $meta->nombre,
            'dependencia_id' => $dependencia->id,
            'dependencia_nombre' => $dependencia->nombre,
            'dependencia_codigo' => $dependencia->codigo,
            'pilar_codigo' => $pilar?->codigo,
            'pilar_nombre' => $pilar?->nombre,
            'programa_codigo' => $programa?->codigo,
            'programa_nombre' => $programa?->nombre,
            'proyectos' => [],
            'fuentes' => [],
            'programacion_fisica' => 0.0,
            'avance_fisico' => 0.0,
            'asignado' => 0.0,
            'comprometido' => 0.0,
            'obligado' => 0.0,
            'pagado' => 0.0,
            'observaciones' => [],
        ];
    }

    private function calcularIndicadores(array $fila): array
    {
        $fila['porcentaje_fisico'] = $fila['programacion_fisica'] > 0 ? ($fila['avance_fisico'] / $fila['programacion_fisica']) * 100 : 0;
        $fila['porcentaje_financiero'] = $fila['asignado'] > 0 ? ($fila['comprometido'] / $fila['asignado']) * 100 : 0;
        $fila['proyectos_count'] = count($fila['proyectos']);
        $fila['fuentes_count'] = count($fila['fuentes']);
        $fila['estado_analitico'] = match (true) {
            $fila['programacion_fisica'] <= 0 && $fila['comprometido'] <= 0 => 'sin_programacion',
            $fila['avance_fisico'] <= 0 && $fila['comprometido'] <= 0 => 'sin_avance',
            $fila['porcentaje_fisico'] >= 120 => 'sobrecumplida',
            $fila['porcentaje_fisico'] >= 100 => 'cumplida',
            default => 'en_ejecucion',
        };

        return $fila;
    }

    /**
     * @param  array{dependencia: int|null, q: string, estado: string}  $filtros
     */
    private function pasaFiltros(array $fila, array $filtros): bool
    {
        if ($filtros['estado'] !== '' && $fila['estado_analitico'] !== $filtros['estado']) {
            return false;
        }

        if ($filtros['q'] === '') {
            return true;
        }

        $q = Str::lower($filtros['q']);
        $texto = Str::lower(implode(' ', [
            $fila['meta_codigo'],
            $fila['meta_nombre'],
            $fila['dependencia_nombre'],
            $fila['pilar_nombre'],
            implode(' ', collect($fila['proyectos'])->pluck('bpin')->all()),
            implode(' ', collect($fila['proyectos'])->pluck('nombre')->all()),
        ]));

        return str_contains($texto, $q);
    }

    /**
     * @param  list<string>  $actuales
     * @return list<string>
     */
    private function agregarObservaciones(array $actuales, ?string $observacion): array
    {
        $observacion = trim((string) $observacion);

        if ($observacion === '' || in_array($observacion, $actuales, true)) {
            return $actuales;
        }

        $actuales[] = $observacion;

        return array_slice($actuales, 0, 5);
    }

    private function pilarDe(MetaProducto $meta): ?PddPilar
    {
        return $this->programaDe($meta)?->linea?->eje?->pilar;
    }

    private function programaDe(MetaProducto $meta): ?PddPrograma
    {
        return $meta->metaResultado?->subprograma?->programa
            ?? $meta->metaResultado?->programa
            ?? $meta->subprograma?->programa;
    }
}
