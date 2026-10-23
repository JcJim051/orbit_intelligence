<?php

namespace App\Services\Analitica;

use App\Models\PasivaRubro;
use Illuminate\Support\Facades\DB;

class ImportadorPasivaAnalitica
{
    public function __construct(private readonly LectorPasivaHacienda $lector) {}

    public function importar(string $ruta, string $extension, ?string $corte = null): int
    {
        $corte = $corte ?: $this->lector->corteDetectado($ruta, $extension);

        if ($corte === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $corte) !== 1) {
            throw new \InvalidArgumentException('Indique el corte de la pasiva con --corte=AAAA-MM-DD.');
        }

        $filas = $this->lector->leer($ruta, $extension);

        DB::transaction(function () use ($filas, $corte): void {
            PasivaRubro::query()->delete();

            foreach (array_chunk($filas, 400) as $lote) {
                PasivaRubro::query()->insert(array_map(
                    fn (array $fila): array => $fila + ['corte' => $corte],
                    $lote,
                ));
            }
        });

        return count($filas);
    }
}
