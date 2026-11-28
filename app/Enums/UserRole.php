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
    case InvestmentManager = 'investment_manager';
    case GoalTracking = 'goal_tracking';
    case InformationReviewer = 'information_reviewer';
    case Webmaster = 'webmaster';
    case BankReviewer = 'bank_reviewer';

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
            self::InvestmentReviewer => 'Admin-inversión',
            self::InvestmentManager => 'Gerente-inversión',
            self::GoalTracking => 'Seguimiento',
            self::InformationReviewer => 'Validador de información',
            self::Webmaster => 'Webmaster SIID',
            self::BankReviewer => 'Revisor de bancos',
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
            self::InvestmentReviewer => 'Administra inversión y estructura PDD; opera únicamente las dependencias asignadas.',
            self::InvestmentManager => 'Administra inversión, estructura PDD y seguimiento para todas las dependencias.',
            self::GoalTracking => 'Diligencia y envía el seguimiento de las dependencias asignadas.',
            self::InformationReviewer => 'Revisa fichas y series de indicadores territoriales, y actúa como Revisor ODS sobre las tareas asignadas.',
            self::Webmaster => 'Administra la publicación, orden y tableros del catálogo público sin modificar fichas ni series.',
            self::BankReviewer => 'Revisa los formatos de banco de las dependencias asignadas y no administra el resto de la plataforma.',
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
            self::InvestmentManager => [
                'Administrar cortes y reportes de todas las dependencias.',
                'Crear y editar la estructura PDD sin eliminar registros.',
                'Revisar, devolver, aprobar y cerrar seguimientos.',
            ],
            self::GoalTracking => [
                'Consultar únicamente las dependencias asignadas.',
                'Diligenciar metas e indicadores del corte.',
                'Enviar la dependencia a revisión.',
            ],
            self::InformationReviewer => [
                'Consultar únicamente los indicadores territoriales asignados.',
                'Revisar y aprobar sus fichas técnicas.',
                'Validar la información histórica antes del seguimiento periódico.',
                'Revisar las relaciones ODS que tenga asignadas, sin confirmarlas definitivamente.',
            ],
            self::Webmaster => [
                'Consultar el catálogo sincronizado de indicadores SIID.',
                'Administrar visibilidad, etiqueta, orden, destacado y tablero.',
                'Consultar sincronizaciones sin modificar fichas ni series.',
            ],
            self::BankReviewer => [
                'Consultar las solicitudes de banco de las dependencias asignadas.',
                'Marcar cada solicitud en proceso, devuelta o aceptada.',
                'No administra usuarios, códigos de radicación ni otros módulos.',
            ],
        };
    }
}
