<?php

namespace App\Models\Concerns;

use App\Enums\EstadoSeguimiento;
use App\Exceptions\CorteCerradoException;
use App\Models\Seguimiento;
use Illuminate\Database\Eloquent\Model;

/**
 * Impide crear, modificar o borrar registros de un seguimiento cerrado, sin importar el rol.
 * Cada modelo indica con seguimientoIdParaCongelamiento() a qué seguimiento pertenece.
 */
trait SeCongelaAlCerrarSeguimiento
{
    public static function bootSeCongelaAlCerrarSeguimiento(): void
    {
        $verificar = function (Model $model): void {
            /** @var Model&SeCongelaAlCerrarSeguimiento $model */
            $seguimientoId = $model->seguimientoIdParaCongelamiento();

            if ($seguimientoId !== null && Seguimiento::query()->whereKey($seguimientoId)->where('estado', EstadoSeguimiento::Cerrado->value)->exists()) {
                throw new CorteCerradoException;
            }
        };

        static::saving($verificar);
        static::deleting($verificar);
    }

    abstract public function seguimientoIdParaCongelamiento(): ?int;
}
