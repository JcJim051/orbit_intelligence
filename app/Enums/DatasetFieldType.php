<?php

namespace App\Enums;

enum DatasetFieldType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Date = 'date';
    case DateTime = 'datetime';
    case Boolean = 'boolean';
    case Select = 'select';
    case MultiSelect = 'multi_select';

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Texto corto',
            self::LongText => 'Texto largo',
            self::Integer => 'Número entero',
            self::Decimal => 'Número decimal',
            self::Date => 'Fecha',
            self::DateTime => 'Fecha y hora',
            self::Boolean => 'Sí / no',
            self::Select => 'Lista de selección',
            self::MultiSelect => 'Selección múltiple',
        };
    }
}
