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

    public function description(): string
    {
        return match ($this) {
            self::Member => 'Consulta y gestiona su trabajo operativo dentro de SIID.',
            self::Reviewer => 'Revisa actas y compromisos institucionales.',
            self::Admin => 'Administra plataforma, usuarios, integraciones, PostGIS y seguridad técnica.',
            self::Manager => 'Aprueba publicaciones, administra permisos funcionales y revisa tableros.',
            self::ManagementSupport => 'Apoya aprobaciones geográficas y seguimiento de procesos institucionales.',
            self::SiidManager => 'Gestiona datos, geovisores, dashboards e indicadores en preparación.',
        };
    }

    /** @return array<int, string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Member => [
                'Consultar actas propias y espacios básicos.',
                'Acceder a inversión pública y seguimiento operativo.',
            ],
            self::Reviewer => [
                'Revisar actas institucionales.',
                'Consultar espacios básicos de trabajo.',
            ],
            self::Admin => [
                'Administrar usuarios, roles e integraciones.',
                'Configurar infraestructura PostGIS y credenciales QGIS.',
                'Crear, editar y publicar geovisores, dashboards e indicadores.',
                'Aprobar publicaciones y administrar plataforma.',
            ],
            self::Manager => [
                'Aprobar publicaciones geográficas, dashboards e indicadores.',
                'Administrar roles funcionales autorizados.',
                'Consultar tableros, geovisores e inversión pública.',
            ],
            self::ManagementSupport => [
                'Apoyar revisión y publicación geográfica.',
                'Consultar espacios de gerencia y seguimiento.',
            ],
            self::SiidManager => [
                'Preparar cargas QGIS, capas, geovisores y datos abiertos.',
                'Crear dashboards, fuentes tabulares e indicadores.',
                'Enviar productos a revisión sin aprobarlos directamente.',
            ],
        };
    }
}
