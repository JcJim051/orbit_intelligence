<?php

namespace App\Http\Controllers;

use App\Models\MicrositioHero;
use App\Models\PasivaRubro;
use App\Services\Analitica\EsquemaAnalitica;
use App\Services\Micrositio\ContenidoMicrositio;
use App\Services\Micrositio\PublicadorMicrositio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MicrositioController extends Controller
{
    public function __construct(private readonly ContenidoMicrositio $contenido) {}

    public function inicio(Request $request): mixed
    {
        return view('micrositio.inicio', [
            'contenido' => $this->contenido->armar($this->preview($request)),
        ]);
    }

    public function indicadores(Request $request): mixed
    {
        $preview = $this->preview($request);
        $contenido = $this->contenido->armar($preview);
        $publicos = array_values(array_filter(
            $contenido['indicadores'],
            fn (array $indicador): bool => $indicador['es_publico'],
        ));
        $codigo = $request->query('i');
        $seleccionado = null;

        if (is_string($codigo) && $codigo !== '') {
            $seleccionado = collect($contenido['indicadores'])->firstWhere('codigo', $codigo);
            abort_if($seleccionado === null || (! $seleccionado['es_publico'] && ! $preview), 404);
        } else {
            $seleccionado = $publicos[0] ?? null;
        }

        return view('micrositio.indicador', [
            'contenido' => $contenido,
            'indicadores' => $publicos,
            'seleccionado' => $seleccionado,
            'preview' => $preview,
        ]);
    }

    public function csv(Request $request, string $codigo): StreamedResponse
    {
        $contenido = $this->contenido->armar($this->preview($request));
        $indicador = collect($contenido['indicadores'])->firstWhere('codigo', $codigo);
        abort_if($indicador === null || ! $indicador['es_publico'], 404);

        $nombre = $codigo.'.csv';

        if ($indicador['conjunto_datos'] === 'pasiva') {
            return response()->streamDownload(function (): void {
                $salida = fopen('php://output', 'w');
                fputcsv($salida, ['corte', 'unidad', 'rubro', 'concepto', 'fuente_codigo', 'fuente', 'grupo_fuente', 'sector', 'bpin', 'es_inversion', 'definitiva', 'certificado', 'comprometido', 'obligado', 'pagado']);

                foreach (PasivaRubro::query()->orderBy('id')->cursor() as $rubro) {
                    fputcsv($salida, [
                        $rubro->corte?->toDateString(),
                        $rubro->unidad,
                        $rubro->rubro,
                        $rubro->concepto,
                        $rubro->fuente_codigo,
                        $rubro->fuente,
                        $rubro->grupo_fuente,
                        $rubro->sector,
                        $rubro->bpin,
                        $rubro->es_inversion,
                        $rubro->definitiva,
                        $rubro->certificado,
                        $rubro->comprometido,
                        $rubro->obligado,
                        $rubro->pagado,
                    ]);
                }

                fclose($salida);
            }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($indicador): void {
            $salida = fopen('php://output', 'w');
            fputcsv($salida, ['codigo', 'nombre', 'descripcion', 'es_ejemplo', 'aviso']);
            fputcsv($salida, [
                $indicador['codigo'],
                $indicador['nombre'],
                $indicador['descripcion'],
                $indicador['es_ejemplo'] ? 1 : 0,
                $indicador['es_ejemplo']
                    ? 'Sin conjunto de datos publicado. Este indicador está marcado como dato de ejemplo.'
                    : 'Sin conjunto de datos publicado para este indicador.',
            ]);
            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function datosAbiertos(): StreamedResponse
    {
        $tabla = EsquemaAnalitica::tabla('v_pasiva_fuente_sector');

        return response()->streamDownload(function () use ($tabla): void {
            $salida = fopen('php://output', 'w');
            fputcsv($salida, ['sector', 'grupo_fuente', 'fuente_codigo', 'fuente', 'definitiva', 'comprometido', 'obligado', 'pagado']);

            foreach (DB::table($tabla)->orderBy('sector')->orderBy('fuente_codigo')->cursor() as $fila) {
                fputcsv($salida, [
                    $fila->sector,
                    $fila->grupo_fuente,
                    $fila->fuente_codigo,
                    $fila->fuente,
                    $fila->definitiva,
                    $fila->comprometido,
                    $fila->obligado,
                    $fila->pagado,
                ]);
            }

            fclose($salida);
        }, 'pasiva.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function archivo(Request $request, int $publicacion): mixed
    {
        $contenido = $this->contenido->armar($this->preview($request));
        $item = collect($contenido['publicaciones'])->firstWhere('id', $publicacion);
        abort_if($item === null, 404);

        $registros = $this->preview($request)
            ? app(PublicadorMicrositio::class)->registrosBorrador('publicaciones')
            : app(PublicadorMicrositio::class)->registrosPublicados('publicaciones');
        $path = collect($registros)->firstWhere('id', $publicacion)['atributos']['archivo_path'] ?? null;
        $nombre = collect($registros)->firstWhere('id', $publicacion)['atributos']['nombre_archivo'] ?? 'publicacion';

        abort_unless(is_string($path) && $this->contenido->rutaSegura($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, (string) $nombre);
    }

    public function banner(Request $request): mixed
    {
        $registros = $this->preview($request)
            ? app(PublicadorMicrositio::class)->registrosBorrador('hero')
            : app(PublicadorMicrositio::class)->registrosPublicados('hero');
        $path = $registros[0]['atributos']['banner_path'] ?? null;

        abort_unless(is_string($path) && $this->contenido->rutaSegura($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function preview(Request $request): bool
    {
        return $request->boolean('preview') && ($request->user()?->can('update', MicrositioHero::class) ?? false);
    }
}
