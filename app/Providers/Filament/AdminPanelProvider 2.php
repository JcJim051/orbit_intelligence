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

        return [
            NavigationItem::make('Resumen')->group('Actas y compromisos')->sort(10)->url(fn (): string => Actas::getUrl())->isActiveWhen(fn (): bool => request()->routeIs('filament.management.pages.actas')),
            NavigationItem::make('Consultar actas')->group('Actas y compromisos')->sort(20)->url(fn (): string => Workspace::getUrl(['workspace' => 'actas']))->isActiveWhen(fn (): bool => request()->route('workspace') === 'actas'),
            NavigationItem::make('Registrar reunión')->group('Actas y compromisos')->sort(30)->url(fn (): string => Workspace::getUrl(['workspace' => 'actas-crear']))->isActiveWhen(fn (): bool => request()->route('workspace') === 'actas-crear'),

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

            NavigationItem::make('Resumen')->group('Inversión pública')->sort(10)->url(fn (): string => Investments::getUrl())->isActiveWhen(fn (): bool => request()->routeIs('filament.management.pages.inversion-publica')),
            NavigationItem::make('Panorama')->group('Inversión pública')->sort(20)->url(fn (): string => Workspace::getUrl(['workspace' => 'inversion']))->isActiveWhen(fn (): bool => request()->route('workspace') === 'inversion'),
            NavigationItem::make('Proyectos')->group('Inversión pública')->sort(30)->url(fn (): string => Workspace::getUrl(['workspace' => 'proyectos']))->isActiveWhen(fn (): bool => request()->route('workspace') === 'proyectos'),
            NavigationItem::make('Entidades y clasificación')->group('Inversión pública')->sort(40)->url(fn (): string => Workspace::getUrl(['workspace' => 'clasificaciones']))->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)->isActiveWhen(fn (): bool => request()->route('workspace') === 'clasificaciones'),

            NavigationItem::make('Vista del módulo')->group('Seguimiento a metas')->sort(10)->badge('Próximamente', 'gray')->url(fn (): string => Goals::getUrl())->visible(fn (): bool => auth()->user()?->canAccessManagementGoals() ?? false)->isActiveWhen(fn (): bool => request()->routeIs('filament.management.pages.metas')),

            NavigationItem::make('Resumen')->group('Administración')->sort(10)->url(fn (): string => PlatformAdministration::getUrl())->visible(fn (): bool => auth()->user()?->canAccessPlatformAdministration() ?? false)->isActiveWhen(fn (): bool => request()->routeIs('filament.management.pages.administracion')),
            NavigationItem::make('Equipo y permisos')->group('Administración')->sort(20)->url(fn (): string => Workspace::getUrl(['workspace' => 'usuarios']))->visible(fn (): bool => auth()->user()?->canAccessPlatformAdministration() ?? false)->isActiveWhen(fn (): bool => request()->route('workspace') === 'usuarios'),
            NavigationItem::make('Google Drive')->group('Administración')->sort(30)->url(fn (): string => Workspace::getUrl(['workspace' => 'drive']))->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)->isActiveWhen(fn (): bool => request()->route('workspace') === 'drive'),
            NavigationItem::make('Dispositivos')->group('Administración')->sort(40)->url(fn (): string => Workspace::getUrl(['workspace' => 'dispositivos']))->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)->isActiveWhen(fn (): bool => request()->route('workspace') === 'dispositivos'),
        ];
    }
}
