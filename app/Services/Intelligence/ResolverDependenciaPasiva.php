<?php

namespace App\Services\Intelligence;

use App\Enums\TipoReglaPasiva;
use App\Models\DependenciaReglaPasiva;
use Illuminate\Support\Collection;

class ResolverDependenciaPasiva
{
    /** @var Collection<int, DependenciaReglaPasiva>|null */
    private ?Collection $reglas = null;

    public function resolver(string $identificacionPresupuestal, string $concepto, ?int $vigencia = null): ?int
    {
        $identificacion = $this->normalizarSeparador($identificacionPresupuestal);
        $unidad = explode(' - ', $identificacion, 2)[0] ?? '';
        $sector = $this->sectorMga($identificacion);

        $coincidencias = $this->reglas()->filter(function (DependenciaReglaPasiva $regla) use ($identificacion, $concepto, $vigencia, $unidad, $sector): bool {
            if (! $this->estaVigente($regla, $vigencia)) {
                return false;
            }

            return match ($regla->tipo_regla) {
                TipoReglaPasiva::UnidadPct => $unidad !== '' && $unidad === trim($regla->valor),
                TipoReglaPasiva::PrefijoRubro => $this->coincidePrefijo($identificacion, $this->normalizarSeparador($regla->valor)),
                TipoReglaPasiva::SectorMga => $sector !== null && $sector === trim($regla->valor),
                TipoReglaPasiva::Bpin => $this->contieneBpin($concepto, trim($regla->valor)),
            };
        });

        $reglasBpin = $coincidencias->filter(
            fn (DependenciaReglaPasiva $regla): bool => $regla->tipo_regla === TipoReglaPasiva::Bpin,
        );

        $ganadora = ($reglasBpin->isNotEmpty() ? $reglasBpin : $coincidencias)
            ->sort(fn (DependenciaReglaPasiva $primera, DependenciaReglaPasiva $segunda): int => $this->compararReglas($primera, $segunda))
            ->first();

        return $ganadora?->dependencia_id;
    }

    /**
     * @return Collection<int, DependenciaReglaPasiva>
     */
    private function reglas(): Collection
    {
        return $this->reglas ??= DependenciaReglaPasiva::query()
            ->where('activo', true)
            ->whereHas('dependencia')
            ->get();
    }

    private function estaVigente(DependenciaReglaPasiva $regla, ?int $vigencia): bool
    {
        if ($vigencia === null) {
            return $regla->vigencia_desde === null && $regla->vigencia_hasta === null;
        }

        if ($regla->vigencia_desde !== null && $regla->vigencia_desde > $vigencia) {
            return false;
        }

        if ($regla->vigencia_hasta !== null && $regla->vigencia_hasta < $vigencia) {
            return false;
        }

        return true;
    }

    private function sectorMga(string $identificacion): ?string
    {
        $resto = explode(' - ', $identificacion, 2)[1] ?? '';
        $rubro = explode(' - ', $resto, 2)[0] ?? '';
        $segmentos = explode('.', $rubro);

        if (count($segmentos) < 3 || $segmentos[0] !== '2' || $segmentos[1] !== '3') {
            return null;
        }

        return $segmentos[2];
    }

    private function coincidePrefijo(string $identificacion, string $valor): bool
    {
        if ($valor === '' || ! str_starts_with($identificacion, $valor)) {
            return false;
        }

        if (strlen($identificacion) === strlen($valor)) {
            return true;
        }

        $siguiente = $identificacion[strlen($valor)];

        return in_array($siguiente, ['.', ' ', '-'], true);
    }

    private function contieneBpin(string $concepto, string $valor): bool
    {
        if ($valor === '') {
            return false;
        }

        return preg_match('/(?<!\d)'.preg_quote($valor, '/').'(?!\d)/u', $concepto) === 1;
    }

    private function compararReglas(DependenciaReglaPasiva $primera, DependenciaReglaPasiva $segunda): int
    {
        $porPrioridad = $segunda->prioridad <=> $primera->prioridad;

        if ($porPrioridad !== 0) {
            return $porPrioridad;
        }

        $porLongitud = mb_strlen(trim($segunda->valor)) <=> mb_strlen(trim($primera->valor));

        if ($porLongitud !== 0) {
            return $porLongitud;
        }

        return $primera->id <=> $segunda->id;
    }

    private function normalizarSeparador(string $value): string
    {
        $normalizado = preg_replace('/\s*-\s*/', ' - ', trim($value));

        return $normalizado ?? trim($value);
    }
}
