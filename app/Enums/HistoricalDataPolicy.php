<?php

namespace App\Enums;

enum HistoricalDataPolicy: string
{
    case FutureOnly = 'future_only';
    case OptionalBackfill = 'optional_backfill';
    case RequiredBackfill = 'required_backfill';
    case Derived = 'derived';

    public function label(): string
    {
        return match ($this) {
            self::FutureOnly => 'Sólo hacia adelante',
            self::OptionalBackfill => 'Actualización histórica opcional',
            self::RequiredBackfill => 'Actualización histórica obligatoria',
            self::Derived => 'Calculado desde otros datos',
        };
    }
}
