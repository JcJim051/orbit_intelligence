<?php

namespace App\Console\Commands;

use App\Models\PoliticaPublicaDepartamental;
use App\Models\Proyecto;
use App\Models\ProyectoTrazador;
use App\Models\Trazador;
use App\Models\TrazadorCategoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\XLSX\Reader;

class ImportTrazadoresPresupuestalesCommand extends Command
{
    protected $signature = 'trazadores:importar {archivo : Ruta del XLSX fuente} {--apply : Escribe los catálogos y asociaciones válidas}';

    protected $description = 'Diagnostica e importa trazadores, categorías, políticas y asociaciones proyecto–trazador por BPIN.';

    public function handle(): int
    {
        $path = (string) $this->argument('archivo');
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            $this->error('Indique un archivo XLSX existente.');

            return self::FAILURE;
        }

        [$marcaciones, $politicas, $diagnosticos, $filasMarcacion] = $this->leer($path);
        $this->table(['Concepto', 'Cantidad'], [
            ['Marcaciones del archivo', $filasMarcacion],
            ['Marcaciones consolidadas', count($marcaciones)],
            ['BPIN distintos', collect($marcaciones)->pluck('bpin')->unique()->count()],
            ['Trazadores distintos', collect($marcaciones)->pluck('trazador')->unique()->count()],
            ['Categorías distintas', collect($marcaciones)->map(fn ($fila) => $fila['trazador'].'|'.$fila['categoria'])->unique()->count()],
            ['Políticas encontradas', count($politicas)],
            ['Diagnósticos', count($diagnosticos)],
        ]);
        foreach ($diagnosticos as $diagnostico) {
            $this->warn($diagnostico);
        }

        $bpines = collect($marcaciones)->pluck('bpin')->unique();
        $proyectos = Proyecto::query()->withoutGlobalScopes()->whereIn('bpin', $bpines)->get()->keyBy('bpin');
        $faltantes = $bpines->reject(fn (string $bpin): bool => $proyectos->has($bpin))->values();
        foreach ($faltantes as $bpin) {
            $this->warn("BPIN inexistente: {$bpin}. No se creará proyecto.");
        }

        if (! $this->option('apply')) {
            $this->info('Diagnóstico terminado. Ejecute de nuevo con --apply para importar las filas válidas.');

            return self::SUCCESS;
        }

        $resultado = DB::transaction(function () use ($marcaciones, $politicas, $proyectos): array {
            foreach ($politicas as $nombre) {
                PoliticaPublicaDepartamental::withTrashed()->updateOrCreate(['nombre' => $nombre], ['activo' => true, 'deleted_at' => null]);
            }
            $politicasPorNombre = PoliticaPublicaDepartamental::query()->get()->keyBy(fn ($p) => $this->normalizar($p->nombre));
            $trazadores = [];
            $categorias = [];
            $asociaciones = [];

            foreach ($marcaciones as $fila) {
                $proyecto = $proyectos->get($fila['bpin']);
                if (! $proyecto) {
                    continue;
                }

                $claveTrazador = $this->normalizar($fila['trazador']);
                if (! isset($trazadores[$claveTrazador])) {
                    $trazador = Trazador::withTrashed()->get()->first(fn ($item) => $this->normalizar($item->nombre) === $claveTrazador);
                    if (! $trazador) {
                        $siguiente = ((int) Trazador::withTrashed()->pluck('codigo')->map(fn (string $codigo): int => (int) preg_replace('/\D/', '', $codigo))->max()) + 1;
                        $trazador = Trazador::query()->create(['codigo' => sprintf('TD-%03d', $siguiente), 'nombre' => $fila['trazador'], 'activo' => true]);
                    } else {
                        $trazador->restore();
                        $trazador->update(['activo' => true]);
                    }
                    $trazadores[$claveTrazador] = $trazador;
                }
                $trazador = $trazadores[$claveTrazador];
                $claveCategoria = $trazador->id.'|'.$this->normalizar($fila['categoria']);
                $categoria = $categorias[$claveCategoria] ?? TrazadorCategoria::withTrashed()->where('trazador_id', $trazador->id)->get()->first(fn ($item) => $this->normalizar($item->nombre) === $this->normalizar($fila['categoria']));
                if (! $categoria) {
                    $categoria = TrazadorCategoria::query()->create(['trazador_id' => $trazador->id, 'nombre' => $fila['categoria'], 'activo' => true]);
                } else {
                    $categoria->restore();
                    $categoria->update(['activo' => true]);
                }
                $categorias[$claveCategoria] = $categoria;

                $politica = blank($fila['politica']) ? null : $politicasPorNombre->get($this->normalizar($fila['politica']));
                $asociacion = ProyectoTrazador::query()->firstOrNew(['proyecto_id' => $proyecto->id, 'trazador_id' => $trazador->id]);
                $asociacion->activo = true;
                if ($politica) {
                    $asociacion->politica_publica_departamental_id = $politica->id;
                }
                $asociacion->save();
                $asociacion->categorias()->updateOrCreate(['trazador_categoria_id' => $categoria->id], ['activo' => true]);
                $asociaciones[$proyecto->id.'|'.$trazador->id.'|'.$categoria->id] = true;
            }

            return ['trazadores' => count($trazadores), 'categorias' => count($categorias), 'asociaciones' => count($asociaciones)];
        });

        $this->info("Importación terminada: {$resultado['trazadores']} trazadores, {$resultado['categorias']} categorías y {$resultado['asociaciones']} asociaciones válidas.");

        return self::SUCCESS;
    }

    /** @return array{0: list<array{bpin:string,trazador:string,politica:?string,categoria:string}>, 1:list<string>, 2:list<string>, 3:int} */
    private function leer(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $marcaciones = [];
        $politicas = [];
        $diagnosticos = [];
        $filasMarcacion = 0;
        $duenoCategoria = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $nombreHoja = $this->normalizar($sheet->getName());
                if ($nombreHoja === 'hoja1') {
                    foreach ($sheet->getRowIterator() as $row) {
                        $valor = trim((string) $this->valor($row->toArray()[0] ?? null));
                        if ($valor !== '') {
                            $politicas[$this->normalizar($valor)] = $valor;
                        }
                    }

                    continue;
                }
                if (! str_contains($nombreHoja, 'trazadores 09')) {
                    continue;
                }

                $ultimoBpin = null;
                $ultimoTrazador = null;
                foreach ($sheet->getRowIterator() as $numero => $row) {
                    if ($numero === 1) {
                        continue;
                    }
                    $celdas = array_map(fn ($celda) => trim((string) $this->valor($celda)), $row->toArray());
                    $bpinCelda = $this->bpin($celdas[4] ?? '');
                    if ($bpinCelda !== '') {
                        $ultimoBpin = $bpinCelda;
                    }
                    $trazadorCelda = trim($celdas[6] ?? '');
                    if ($trazadorCelda !== '') {
                        $ultimoTrazador = $trazadorCelda;
                    }
                    $categoria = trim($celdas[8] ?? '');
                    if ($categoria !== '') {
                        $filasMarcacion++;
                    }
                    if ($ultimoBpin === null && $categoria !== '') {
                        $diagnosticos[] = "Fila {$numero}: categoría sin BPIN.";

                        continue;
                    }
                    if ($categoria === '') {
                        continue;
                    }
                    if ($ultimoTrazador === null) {
                        $diagnosticos[] = "Fila {$numero}: trazador vacío para BPIN {$ultimoBpin}.";

                        continue;
                    }
                    $claveNombreCategoria = $this->normalizar($categoria);
                    $claveNombreTrazador = $this->normalizar($ultimoTrazador);
                    if (isset($duenoCategoria[$claveNombreCategoria]) && $duenoCategoria[$claveNombreCategoria] !== $claveNombreTrazador) {
                        $diagnosticos[] = "Fila {$numero}: la categoría «{$categoria}» aparece bajo un trazador distinto; se excluye por inconsistencia.";

                        continue;
                    }
                    $duenoCategoria[$claveNombreCategoria] = $claveNombreTrazador;
                    $clave = $ultimoBpin.'|'.$this->normalizar($ultimoTrazador).'|'.$this->normalizar($categoria);
                    $marcaciones[$clave] = ['bpin' => $ultimoBpin, 'trazador' => $ultimoTrazador, 'politica' => trim($celdas[7] ?? '') ?: null, 'categoria' => $categoria];
                }
            }
        } finally {
            $reader->close();
        }

        return [array_values($marcaciones), array_values($politicas), $diagnosticos, $filasMarcacion];
    }

    private function valor(mixed $celda): mixed
    {
        return $celda instanceof Cell ? $celda->getValue() : $celda;
    }

    private function normalizar(string $valor): string
    {
        return Str::of(Str::ascii($valor))->lower()->squish()->toString();
    }

    private function bpin(string $valor): string
    {
        return preg_replace('/\.0$/', '', preg_replace('/[^0-9.]/', '', $valor)) ?: '';
    }
}
