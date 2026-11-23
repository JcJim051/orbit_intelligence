<?php

namespace App\Services\Intelligence\IndicadoresTerritoriales;

use App\Models\SeguimientoConfiguracion;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ConfiguracionReporteIndicadores
{
    public function activo(): bool
    {
        if (! Schema::hasTable('seguimiento_configuraciones')) {
            return true;
        }

        return (bool) (SeguimientoConfiguracion::query()
            ->whereKey(1)
            ->value('reporte_indicadores_activo') ?? true);
    }

    public function obtener(): ?SeguimientoConfiguracion
    {
        if (! Schema::hasTable('seguimiento_configuraciones')) {
            return null;
        }

        return SeguimientoConfiguracion::query()->with('actualizadoPor')->find(1);
    }

    public function actualizar(bool $activo, User $usuario): SeguimientoConfiguracion
    {
        $configuracion = SeguimientoConfiguracion::query()->updateOrCreate(
            ['id' => 1],
            [
                'reporte_indicadores_activo' => $activo,
                'updated_by' => $usuario->id,
            ],
        );

        return $configuracion->load('actualizadoPor');
    }

    public function edicionValidadoresActiva(): bool
    {
        if (! Schema::hasTable('seguimiento_configuraciones')) {
            return true;
        }

        return (bool) (SeguimientoConfiguracion::query()
            ->whereKey(1)
            ->value('edicion_validadores_informacion_activa') ?? true);
    }

    public function actualizarTodo(bool $reporteActivo, bool $edicionValidadoresActiva, User $usuario): SeguimientoConfiguracion
    {
        $configuracion = SeguimientoConfiguracion::query()->updateOrCreate(
            ['id' => 1],
            [
                'reporte_indicadores_activo' => $reporteActivo,
                'edicion_validadores_informacion_activa' => $edicionValidadoresActiva,
                'updated_by' => $usuario->id,
            ],
        );

        return $configuracion->load('actualizadoPor');
    }
}
