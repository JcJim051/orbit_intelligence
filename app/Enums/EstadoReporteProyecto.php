<?php

namespace App\Enums;

enum EstadoReporteProyecto: string
{
    case Borrador = 'borrador';
    case Reportado = 'reportado';
    case Devuelto = 'devuelto';
    case Aprobado = 'aprobado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'En diligenciamiento',
            self::Reportado => 'Reportado',
            self::Devuelto => 'Devuelto',
            self::Aprobado => 'Aprobado',
        };
    }

    /**
     * El sector solo puede editar mientras el reporte no se haya enviado o cuando la Gerencia lo devuelve.
     */
    public function esEditablePorSector(): bool
    {
        return in_array($this, [self::Borrador, self::Devuelto], true);
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Borrador => '',
            self::Reportado => 'status-transcribing',
            self::Devuelto => 'status-error',
            self::Aprobado => 'status-approved',
        };
    }
}
