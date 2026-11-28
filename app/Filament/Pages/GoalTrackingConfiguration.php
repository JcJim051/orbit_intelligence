<?php

namespace App\Filament\Pages;

use App\Services\Intelligence\IndicadoresTerritoriales\ConfiguracionReporteIndicadores;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class GoalTrackingConfiguration extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $title = 'Configuración del seguimiento';

    protected static ?string $slug = 'administracion/configuracion-seguimiento';

    protected string $view = 'filament.pages.goal-tracking-configuration';

    public bool $reporteIndicadoresActivo = true;

    public bool $edicionValidadoresInformacionActiva = true;

    public ?string $actualizadoPor = null;

    public ?string $actualizadoAt = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(ConfiguracionReporteIndicadores $configuracion): void
    {
        $this->reporteIndicadoresActivo = $configuracion->activo();
        $this->edicionValidadoresInformacionActiva = $configuracion->edicionValidadoresActiva();
        $this->actualizarMetadatos($configuracion);
    }

    public function save(ConfiguracionReporteIndicadores $configuracion): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $configuracion->actualizarTodo(
            $this->reporteIndicadoresActivo,
            $this->edicionValidadoresInformacionActiva,
            auth()->user(),
        );
        $this->actualizarMetadatos($configuracion);

        Notification::make()
            ->title('Configuración del seguimiento actualizada')
            ->body(($this->reporteIndicadoresActivo
                ? 'Reporte de indicadores activo. '
                : 'Reporte de indicadores apagado. ')
                .($this->edicionValidadoresInformacionActiva
                    ? 'Los validadores conservan permisos de edición durante la preparación.'
                    : 'La edición de los validadores quedó bloqueada; podrán continuar revisando y aprobando.'))
            ->success()
            ->send();
    }

    private function actualizarMetadatos(ConfiguracionReporteIndicadores $configuracion): void
    {
        $registro = $configuracion->obtener();
        $this->actualizadoPor = $registro?->actualizadoPor?->name;
        $this->actualizadoAt = $registro?->updated_at?->format('d/m/Y H:i');
    }
}
