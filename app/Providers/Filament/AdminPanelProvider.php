<?php

namespace App\Providers\Filament;

use App\Filament\Clusters\Geography\Pages\Overview as GeographyOverview;
use App\Filament\Pages\Actas;
use App\Filament\Pages\Dashboards;
use App\Filament\Pages\Goals;
use App\Filament\Pages\Home;
use App\Filament\Pages\Investments;
use App\Filament\Pages\PlatformAdministration;
use App\Filament\Pages\Workspace;
use App\Filament\Resources\UserResource;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\SetManagementLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('management')
            ->path('gestion')
            ->brandName('SIID 2.0')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->viteTheme('resources/css/filament/management/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Home::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->navigationGroups($this->moduleNavigationGroups())
            ->navigationItems($this->moduleNavigationItems())
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.application-assets'))
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('filament.navigation-defaults'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                SetManagementLocale::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureActiveUser::class,
            ]);
    }

    /** @return array<NavigationGroup> */
    private function moduleNavigationGroups(): array
    {
        return [
            NavigationGroup::make('Actas y compromisos')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->collapsed(),
            NavigationGroup::make('Inteligencia geográfica')
                ->icon(Heroicon::OutlinedMap)
                ->collapsed(),
            NavigationGroup::make('Dashboards')
                ->icon(Heroicon::OutlinedPresentationChartBar)
                ->collapsed(),
            NavigationGroup::make('Indicadores')
                ->icon(Heroicon::OutlinedPresentationChartBar)
                ->collapsed(),
            NavigationGroup::make('Inversión pública')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->collapsed(),
            NavigationGroup::make('Estructura plan PDD')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->collapsed(),
            NavigationGroup::make('Seguimiento a metas')
                ->icon(Heroicon::OutlinedFlag)
                ->collapsed(),
            NavigationGroup::make('Administración')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->collapsed(),
        ];
    }

    /** @return array<NavigationItem> */
    private function moduleNavigationItems(): array
    {
        $spatialAccess = fn (): bool => auth()->user()?->canAccessSpatialGovernance() ?? false;
        $dashboardAccess = fn (): bool => auth()->user()?->canManageDashboards() ?? false;
        $goalsAccess = fn (): bool => auth()->user()?->canAccessManagementGoals() ?? false;
        $odsReviewAccess = fn (): bool => auth()->user()?->canReviewOdsIndicators() ?? false;
        $generalAccess = fn (): bool => ! (auth()->user()?->isDedicatedOdsReviewer() ?? false);
        $goalsCatalogAccess = fn (): bool => (auth()->user()?->canAccessManagementGoals() ?? false) && ! (auth()->user()?->isDedicatedOdsReviewer() ?? false);

        return [
            NavigationItem::make('Resumen')->group('Actas y compromisos')->sort(10)->url(fn (): string => Actas::getUrl())->visible($generalAccess)->isActiveWhen(fn (): bool => request()->routeIs('filament.management.pages.actas')),
            NavigationItem::make('Consultar actas')->group('Actas y compromisos')->sort(20)->url(fn (): string => Workspace::getUrl(['workspace' => 'actas']))->visible($generalAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'actas'),
            NavigationItem::make('Registrar reunión')->group('Actas y compromisos')->sort(30)->url(fn (): string => Workspace::getUrl(['workspace' => 'actas-crear']))->visible($generalAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'actas-crear'),

            NavigationItem::make('Bandeja SIG')->group('Inteligencia geográfica')->sort(10)->url(fn (): string => GeographyOverview::getUrl())->visible($spatialAccess)->isActiveWhen(fn (): bool => request()->routeIs('filament.management.geografia.pages.inicio')),
            NavigationItem::make('Cargas desde QGIS')->group('Inteligencia geográfica')->sort(20)->url(fn (): string => Workspace::getUrl(['workspace' => 'cargas-qgis']))->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)->isActiveWhen(fn (): bool => request()->route('workspace') === 'cargas-qgis'),
            NavigationItem::make('Catálogo de datos')->group('Inteligencia geográfica')->sort(30)->url(fn (): string => Workspace::getUrl(['workspace' => 'catalogo-datos']))->visible($spatialAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'catalogo-datos'),
            NavigationItem::make('Capas y geovisores')->group('Inteligencia geográfica')->sort(40)->url(fn (): string => Workspace::getUrl(['workspace' => 'geovisores']))->visible($spatialAccess)->isActiveWhen(fn (): bool => request()->routeIs('filament.management.pages.espacios.*') && request()->route('workspace') === 'geovisores'),
            NavigationItem::make('Datos abiertos')->group('Inteligencia geográfica')->sort(50)->url(fn (): string => Workspace::getUrl(['workspace' => 'fuentes-abiertas']))->visible($spatialAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'fuentes-abiertas'),
            NavigationItem::make('Infraestructura')->group('Inteligencia geográfica')->sort(60)->url(fn (): string => Workspace::getUrl(['workspace' => 'infraestructura']))->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)->isActiveWhen(fn (): bool => request()->route('workspace') === 'infraestructura'),

            NavigationItem::make('Resumen')->group('Dashboards')->sort(10)->url(fn (): string => Dashboards::getUrl())->visible($dashboardAccess)->isActiveWhen(fn (): bool => request()->routeIs('filament.management.pages.dashboards')),
            NavigationItem::make('Administrar tableros')->group('Dashboards')->sort(20)->url(fn (): string => Workspace::getUrl(['workspace' => 'tableros']))->visible($dashboardAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'tableros'),
            NavigationItem::make('Fuentes tabulares')->group('Dashboards')->sort(30)->url(fn (): string => Workspace::getUrl(['workspace' => 'fuentes-tabulares']))->visible($dashboardAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'fuentes-tabulares'),

            NavigationItem::make('Bandeja de indicadores')->group('Indicadores')->sort(10)->url(fn (): string => Workspace::getUrl(['workspace' => 'indicadores']))->visible($dashboardAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'indicadores'),

            NavigationItem::make('Resumen')->group('Inversión pública')->sort(10)->url(fn (): string => Investments::getUrl())->visible($generalAccess)->isActiveWhen(fn (): bool => request()->routeIs('filament.management.pages.inversion-publica')),
            NavigationItem::make('Panorama')->group('Inversión pública')->sort(20)->url(fn (): string => Workspace::getUrl(['workspace' => 'inversion']))->visible($generalAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'inversion'),
            NavigationItem::make('Proyectos')->group('Inversión pública')->sort(30)->url(fn (): string => Workspace::getUrl(['workspace' => 'proyectos']))->visible($generalAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'proyectos'),
            NavigationItem::make('Entidades y clasificación')->group('Inversión pública')->sort(40)->url(fn (): string => Workspace::getUrl(['workspace' => 'clasificaciones']))->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)->isActiveWhen(fn (): bool => request()->route('workspace') === 'clasificaciones'),

            NavigationItem::make('Inicio del módulo')->group('Seguimiento a metas')->sort(10)->url(fn (): string => Goals::getUrl())->visible($goalsAccess)->isActiveWhen(fn (): bool => request()->routeIs('filament.management.pages.metas')),
            NavigationItem::make('Reporte mensual')->group('Seguimiento a metas')->sort(20)->url(fn (): string => Workspace::getUrl(['workspace' => 'reporte-mensual']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'reporte-mensual'),
            NavigationItem::make('Dependencias')->group('Seguimiento a metas')->sort(30)->url(fn (): string => Workspace::getUrl(['workspace' => 'seguimiento-dependencias']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => in_array(request()->route('workspace'), ['seguimiento-dependencias', 'seguimiento-dependencia', 'seguimiento-dependencia-reportar', 'seguimiento-proyecto-reportar'], true)),
            NavigationItem::make('Reglas de pasiva')->group('Seguimiento a metas')->sort(50)->url(fn (): string => Workspace::getUrl(['workspace' => 'reglas-pasiva']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'reglas-pasiva'),
            NavigationItem::make('Proyectos')->group('Seguimiento a metas')->sort(60)->url(fn (): string => Workspace::getUrl(['workspace' => 'metas-proyectos']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => in_array(request()->route('workspace'), ['metas-proyectos', 'metas-proyecto'], true)),

            NavigationItem::make('Pilares PDD')->group('Estructura plan PDD')->sort(10)->url(fn (): string => Workspace::getUrl(['workspace' => 'pilares']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'pilares'),
            NavigationItem::make('Ejes PDD')->group('Estructura plan PDD')->sort(20)->url(fn (): string => Workspace::getUrl(['workspace' => 'ejes']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'ejes'),
            NavigationItem::make('Líneas PDD')->group('Estructura plan PDD')->sort(30)->url(fn (): string => Workspace::getUrl(['workspace' => 'lineas']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'lineas'),
            NavigationItem::make('Programas PDD')->group('Estructura plan PDD')->sort(40)->url(fn (): string => Workspace::getUrl(['workspace' => 'programas']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'programas'),
            NavigationItem::make('Subprogramas PDD')->group('Estructura plan PDD')->sort(50)->url(fn (): string => Workspace::getUrl(['workspace' => 'subprogramas']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'subprogramas'),
            NavigationItem::make('Sectores MGA')->group('Estructura plan PDD')->sort(60)->url(fn (): string => Workspace::getUrl(['workspace' => 'sectores-mga']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'sectores-mga'),
            NavigationItem::make('Plan indicativo')->group('Estructura plan PDD')->sort(70)->url(fn (): string => Workspace::getUrl(['workspace' => 'plan-indicativo']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'plan-indicativo'),
            NavigationItem::make('Metas de producto')->group('Estructura plan PDD')->sort(80)->url(fn (): string => Workspace::getUrl(['workspace' => 'metas-producto']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'metas-producto'),
            NavigationItem::make('Indicadores de resultado')->group('Estructura plan PDD')->sort(90)->url(fn (): string => Workspace::getUrl(['workspace' => 'indicadores-resultado']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'indicadores-resultado'),
            NavigationItem::make('Metas por pilar')->group('Estructura plan PDD')->sort(100)->url(fn (): string => Workspace::getUrl(['workspace' => 'metas-resultado-por-pilar']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'metas-resultado-por-pilar'),
            NavigationItem::make('Metas de resultado')->group('Estructura plan PDD')->sort(110)->url(fn (): string => Workspace::getUrl(['workspace' => 'metas-resultado']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'metas-resultado'),
            NavigationItem::make('Dependencias')->group('Estructura plan PDD')->sort(120)->url(fn (): string => Workspace::getUrl(['workspace' => 'dependencias']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'dependencias'),
            NavigationItem::make('Municipios')->group('Estructura plan PDD')->sort(130)->url(fn (): string => Workspace::getUrl(['workspace' => 'municipios']))->visible($goalsCatalogAccess)->isActiveWhen(fn (): bool => request()->route('workspace') === 'municipios'),
            NavigationItem::make('Revisión ODS')->group('Estructura plan PDD')->sort(140)->url(fn (): string => Workspace::getUrl(['workspace' => 'revision-ods']))->visible($odsReviewAccess)->isActiveWhen(fn (): bool => in_array(request()->route('workspace'), ['revision-ods', 'revision-ods-detalle'], true)),

            NavigationItem::make('Resumen')->group('Administración')->sort(10)->url(fn (): string => PlatformAdministration::getUrl())->visible(fn (): bool => auth()->user()?->canAccessPlatformAdministration() ?? false)->isActiveWhen(fn (): bool => request()->routeIs('filament.management.pages.administracion')),
            NavigationItem::make('Equipo y permisos')->group('Administración')->sort(20)->url(fn (): string => UserResource::getUrl())->visible(fn (): bool => auth()->user()?->canAccessPlatformAdministration() ?? false)->isActiveWhen(fn (): bool => request()->routeIs('filament.management.resources.usuarios.*') || request()->route('workspace') === 'usuarios'),
            NavigationItem::make('Google Drive')->group('Administración')->sort(30)->url(fn (): string => Workspace::getUrl(['workspace' => 'drive']))->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)->isActiveWhen(fn (): bool => request()->route('workspace') === 'drive'),
            NavigationItem::make('Dispositivos')->group('Administración')->sort(40)->url(fn (): string => Workspace::getUrl(['workspace' => 'dispositivos']))->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)->isActiveWhen(fn (): bool => request()->route('workspace') === 'dispositivos'),
        ];
    }
}
