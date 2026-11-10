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
    case OdsReviewer = 'ods_reviewer';
    case OdsValidator = 'ods_validator';
    case InvestmentReviewer = 'investment_reviewer';
    case InformationReviewer = 'information_reviewer';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Usuario',
            self::Reviewer => 'Revisor de actas',
            self::Admin => 'Administrador técnico',
            self::Manager => 'Gerente',
            self::ManagementSupport => 'Apoyo administrativo de Gerencia',
            self::SiidManager => 'Gestor SIID',
            self::OdsReviewer => 'Revisor ODS',
            self::OdsValidator => 'Validador ODS',
            self::InvestmentReviewer => 'Revisor de inversión',
            self::InformationReviewer => 'Validador de información',
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
            self::OdsReviewer => 'Revisa indicadores de resultado y propone o rechaza relaciones con indicadores ODS.',
            self::OdsValidator => 'Confirma las relaciones ODS propuestas por el equipo revisor.',
            self::InvestmentReviewer => 'Revisa y corrige catálogos, proyectos y seguimientos de inversión sin eliminar información.',
            self::InformationReviewer => 'Revisa fichas y series de indicadores territoriales, y actúa como Revisor ODS sobre las tareas asignadas.',
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
            self::OdsReviewer => [
                'Revisar relaciones entre indicadores PDD e indicadores ODS.',
                'Crear propuestas, comentar y rechazar sugerencias no pertinentes.',
                'No puede confirmar relaciones definitivas.',
            ],
            self::OdsValidator => [
                'Validar y confirmar relaciones ODS propuestas por revisores.',
                'Rechazar propuestas no pertinentes con trazabilidad.',
                'Consultar el módulo de seguimiento a metas.',
            ],
            self::InvestmentReviewer => [
                'Crear y editar metas, indicadores, proyectos y resultados.',
                'Cargar plantillas de seguimiento mensual.',
                'Revisar construcción de metas resultado sin eliminar registros.',
            ],
            self::InformationReviewer => [
                'Consultar únicamente los indicadores territoriales asignados.',
                'Revisar y aprobar sus fichas técnicas.',
                'Validar la información histórica antes del seguimiento periódico.',
                'Revisar las relaciones ODS que tenga asignadas, sin confirmarlas definitivamente.',
            ],
        };
    }
}
