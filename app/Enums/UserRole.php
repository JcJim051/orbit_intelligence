<?php

namespace App\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Reviewer = 'reviewer';
    case Admin = 'admin';
    case Manager = 'manager';
    case ManagementSupport = 'management_support';
    case SiidManager = 'siid_manager';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Usuario',
            self::Reviewer => 'Revisor de actas',
            self::Admin => 'Administrador técnico',
            self::Manager => 'Gerente',
            self::ManagementSupport => 'Apoyo administrativo de Gerencia',
            self::SiidManager => 'Gestor SIID',
        };
    }
}
