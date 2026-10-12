<?php

namespace App\Filament\Resources\AnalisisAreaResource\Pages;

use App\Enums\ModoAnalisis;
use App\Enums\OrigenGeometriaAnalisis;
use App\Filament\Resources\AnalisisAreaResource;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InvestmentProject;
use App\Models\User;
use App\Services\InteligenciaGeografica\CrearAnalisisArea;
use App\Services\InteligenciaGeografica\InformeAnalisis;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MapaAnalisis extends Page
{
    protected static string $resource = AnalisisAreaResource::class;

    protected static ?string $title = 'Análisis de área';

    protected static ?string $navigationLabel = 'Análisis de área';

    protected string $view = 'filament.inteligencia.mapa-analisis';

    public ?string $analisisId = null;

    public ?string $geojson = null;

    public ?string $nombreArchivo = null;

    public ?string $origen = null;

    public int $sridOrigen = 4326;

    public ?string $proyectoId = null;

    public string $modo = 'normal';

    public string $busquedaProyecto = '';

    public ?string $areaHa = null;

    public ?string $mensaje = null;

    public ?string $poligono = null;

    /** @var list<array<string, mixed>> */
    public array $filas = [];

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $identificador = request()->query('analisis');

        if (! is_scalar($identificador) || (string) $identificador === '') {
            return;
        }

        $analisis = AnalisisArea::query()->findOrFail((string) $identificador);
        abort_unless(auth()->user()?->can('view', $analisis), 403);
        $this->cargar($analisis);
    }

    public function procesar(CrearAnalisisArea $crear, InformeAnalisis $informe): void
    {
        abort_unless(auth()->user()?->can('create', AnalisisArea::class), 403);
        $this->mensaje = null;

        /** @var User $usuario */
        $usuario = auth()->user();
        $origen = is_string($this->origen) ? OrigenGeometriaAnalisis::tryFrom($this->origen) : null;

        try {
            $analisis = $crear->crear(
                $usuario,
                ModoAnalisis::from($this->modo),
                $this->proyectoId !== '' ? $this->proyectoId : null,
                $this->geojson,
                $this->nombreArchivo,
                null,
                $this->sridOrigen,
                $origen,
            );
        } catch (ValidationException $excepcion) {
            $this->mensaje = collect($excepcion->errors())->flatten()->first();

            return;
        }

        $this->cargar($analisis, $informe);
        $this->dispatch('analisis-pintado', estado: $this->estadoMapa($informe));
    }

    /**
     * @return array<string, mixed>
     */
    public function estadoMapa(?InformeAnalisis $informe = null): array
    {
        $informe ??= app(InformeAnalisis::class);
        $capas = [];

        if ($this->analisisId !== null) {
            $analisis = AnalisisArea::query()->find($this->analisisId);

            if ($analisis instanceof AnalisisArea) {
                $capas = $informe->geometrias($analisis);
            }
        }

        return [
            'poligono' => $this->poligono,
            'areaHa' => $this->areaHa,
            'capas' => $capas,
        ];
    }

    /**
     * @return Collection<int, InvestmentProject>
     */
    public function proyectosVisibles()
    {
        $consulta = InvestmentProject::query()->orderBy('bpin')->limit(30);
        $texto = trim($this->busquedaProyecto);

        if ($texto !== '') {
            $operador = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $consulta->where(function ($inner) use ($texto, $operador): void {
                $inner->where('bpin', $operador, "%{$texto}%")
                    ->orWhere('name', $operador, "%{$texto}%");
            });
        }

        return $consulta->get();
    }

    protected function getViewData(): array
    {
        return [
            'proyectos' => $this->proyectosVisibles(),
            'estadoInicial' => $this->estadoMapa(),
            'historialUrl' => AnalisisAreaResource::getUrl('historial'),
        ];
    }

    private function cargar(AnalisisArea $analisis, ?InformeAnalisis $informe = null): void
    {
        $informe ??= app(InformeAnalisis::class);
        $this->analisisId = (string) $analisis->id;
        $this->proyectoId = $analisis->investment_project_id;
        $this->modo = $analisis->modo->value;
        $this->poligono = $analisis->geoJson4326();
        $this->geojson = $this->poligono;
        $hectareas = $analisis->areaHectareas();
        $this->areaHa = $hectareas === null ? null : number_format($hectareas, 2, ',', '.');
        $this->filas = $informe->filas($analisis)->values()->all();
    }
}
