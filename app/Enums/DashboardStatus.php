<?php

namespace App\Enums;

enum DashboardStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::PendingReview => 'Pendiente de revisión',
            self::Published => 'Publicado',
            self::Archived => 'Archivado',
        };
    }
}
