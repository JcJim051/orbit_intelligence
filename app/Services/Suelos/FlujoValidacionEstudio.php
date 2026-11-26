<?php

namespace App\Services\Suelos;

use App\Models\Suelos\EstadoValidacion;
use App\Models\Suelos\EstudioSuelos;
use App\Models\Suelos\EstudioValidacionHistorial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlujoValidacionEstudio
{
    public function enviarARevision(EstudioSuelos $estudio, User $usuario): void
    {
        $this->transicionar($estudio, $usuario, 'EN_REVISION', null, false);
    }

    public function validar(EstudioSuelos $estudio, User $usuario, ?string $comentario): void
    {
        $this->exigirRevisorDistinto($estudio, $usuario);
        $this->transicionar($estudio, $usuario, 'VALIDADO', $comentario, true);
    }

    public function rechazar(EstudioSuelos $estudio, User $usuario, string $comentario): void
    {
        if (trim($comentario) === '') {
            throw ValidationException::withMessages([
                'comentario' => 'El rechazo exige un comentario.',
            ]);
        }

        $this->exigirRevisorDistinto($estudio, $usuario);
        $this->transicionar($estudio, $usuario, 'RECHAZADO', $comentario, true);
    }

    private function exigirRevisorDistinto(EstudioSuelos $estudio, User $usuario): void
    {
        if (! $usuario->can('validar', $estudio)) {
            throw ValidationException::withMessages([
                'estudio' => 'Quien cargó el estudio no puede validarlo ni rechazarlo.',
            ]);
        }
    }

    private function transicionar(EstudioSuelos $estudio, User $usuario, string $codigoEstado, ?string $comentario, bool $registraRevisor): void
    {
        $estado = EstadoValidacion::query()->where('codigo', $codigoEstado)->firstOrFail();

        DB::transaction(function () use ($estudio, $usuario, $estado, $comentario, $registraRevisor): void {
            $anterior = $estudio->estado_validacion_id;

            $estudio->estado_validacion_id = $estado->id;

            if ($registraRevisor) {
                $estudio->revisado_por = $usuario->id;
                $estudio->fecha_revision = now();
                $estudio->observacion_revision = $comentario;
            }

            $estudio->save();

            EstudioValidacionHistorial::query()->create([
                'estudio_id' => $estudio->id,
                'estado_anterior_id' => $anterior,
                'estado_nuevo_id' => $estado->id,
                'usuario_id' => $usuario->id,
                'observacion' => $comentario,
            ]);
        });
    }
}
