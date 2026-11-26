<?php

namespace App\Services\Micrositio\Mapa;

use App\Contracts\Micrositio\FuenteDatosMapaInicio;
use App\Models\MicrositioMapaCapa;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FuenteBaseDatosMapaInicio implements FuenteDatosMapaInicio
{
    public function __construct(private readonly FuenteJsonMapaInicio $archivos) {}

    public function metadatos(): array
    {
        $ultima = $this->capasActivas()->sortByDesc('sincronizada_en')->first()?->sincronizada_en;

        return [
            'version' => $ultima?->format('Y-m-d-His') ?? 'bootstrap-2026',
            'departamento' => ['divipola' => '50', 'nombre' => 'Meta'],
        ];
    }

    public function capas(): array
    {
        $capas = $this->capasActivas();

        if ($capas->isNotEmpty()) {
            return $capas->map->comoCapaPublica()->values()->all();
        }

        $poblacion = $this->archivos->capa('POB-2026');

        return $poblacion === null ? [] : [[
            ...$poblacion,
            'rotulo_corto' => 'Población 2026',
            'unidad_etiqueta' => 'Habitantes',
            'fuente_dataset_id' => null,
            'fuente_actualizada_en' => '2025-07-30T00:00:00-05:00',
            'sincronizada_en' => null,
            'estado_frescura' => 'bootstrap',
        ]];
    }

    public function capa(string $codigo): ?array
    {
        foreach ($this->capas() as $capa) {
            if (($capa['codigo'] ?? null) === $codigo) {
                return $capa;
            }
        }

        return null;
    }

    public function geometriaMunicipios(): array
    {
        return $this->archivos->geometriaMunicipios();
    }

    public function geometriaColombia(): array
    {
        return $this->archivos->geometriaColombia();
    }

    /** @return Collection<int, MicrositioMapaCapa> */
    private function capasActivas(): Collection
    {
        try {
            if (! Schema::hasTable('micrositio_mapa_capas')) {
                return new Collection;
            }

            return MicrositioMapaCapa::query()
                ->where('activa', true)
                ->orderBy('orden')
                ->orderBy('id')
                ->get();
        } catch (Throwable) {
            return new Collection;
        }
    }
}
