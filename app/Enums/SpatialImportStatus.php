<?php

namespace App\Enums;

enum SpatialImportStatus: string
{
    case Pending = 'pending';
    case StagingReady = 'staging_ready';
    case Profiled = 'profiled';
    case ContractDraft = 'contract_draft';
    case Approved = 'approved';
    case Closed = 'closed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Preparando',
            self::StagingReady => 'Listo para QGIS',
            self::Profiled => 'Perfilado',
            self::ContractDraft => 'Contrato en borrador',
            self::Approved => 'Aprobada para publicación',
            self::Closed => 'Cerrado',
            self::Failed => 'Falló',
        };
    }
}
