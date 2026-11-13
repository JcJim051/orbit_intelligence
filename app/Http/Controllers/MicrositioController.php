<?php

namespace App\Http\Controllers;

use App\Models\MicrositioHero;
use App\Models\PasivaRubro;
use App\Models\SiidIndicador;
use App\Models\SiidPublicacion;
use App\Models\TabularDataSourceVersion;
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
        $publicacionesIndicadores = SiidPublicacion::query()
            ->publicas()
            ->where('destacado', true)
            ->with('publicable')
            ->get()
            ->filter(fn (SiidPublicacion $publicacion): bool => $publicacion->publicableIsActive())
            ->values();

        $publicacionesIndicadores->each(function (SiidPublicacion $publicacion): void {
            if ($publicacion->publicable instanceof SiidIndicador) {
                $publicacion->publicable->loadMissing(['area', 'eje']);
            }
        });

        $indicadoresDestacadosPorEje = $publicacionesIndicadores
            ->groupBy(function (SiidPublicacion $publicacion): string {
                $indicador = $publicacion->publicable;

                return $indicador instanceof SiidIndicador
                    ? ($indicador->eje?->nombre ?? 'Por clasificar')
                    : 'Indicadores territoriales';
            })
            ->map(fn ($publicaciones) => $publicaciones->groupBy(function (SiidPublicacion $publicacion): string {
                $indicador = $publicacion->publicable;

                return $indicador instanceof SiidIndicador
                    ? ($indicador->area?->nombre ?? 'Por clasificar')
                    : ($indicador->area_tematica ?? 'Por clasificar');
            })->sortKeys())
            ->sortKeys();

        return view('micrositio.inicio', [
            'contenido' => $this->contenido->armar($this->preview($request)),
            'indicadoresDestacadosPorEje' => $indicadoresDestacadosPorEje,
        ]);
    }

    public function publicaciones(Request $request): mixed
    {
        return view('micrositio.publicaciones', [
            'contenido' => $this->contenido->armar($this->preview($request)),
        ]);
    }

    public function politicas(Request $request, PublicadorMicrositio $publicador): mixed
    {
        $preview = $this->preview($request);
        $registros = $preview ? $publicador->registrosBorrador('politicas') : $publicador->registrosPublicados('politicas');
        $politicas = array_column($registros, 'atributos');
        $slug = $request->query('politica');
        $seleccionada = null;
        if (is_string($slug) && $slug !== '') {
            $seleccionada = collect($politicas)->firstWhere('slug', $slug);
            abort_if($seleccionada === null, 404);
        }

        return view('micrositio.politicas', [
            'contenido' => $this->contenido->armar($preview),
            'politicas' => $politicas,
            'seleccionada' => $seleccionada,
            'preview' => $preview,
        ]);
    }

    public function documentoPolitica(Request $request, string $slug, int $indice, PublicadorMicrositio $publicador): mixed
    {
        $registros = $this->preview($request)
            ? $publicador->registrosBorrador('politicas')
            : $publicador->registrosPublicados('politicas');
        $politica = collect(array_column($registros, 'atributos'))->firstWhere('slug', $slug);
        $path = $politica['documentos'][$indice] ?? null;
        abort_unless(is_string($path) && str_starts_with($path, 'micrositio/politicas/') && $this->contenido->rutaSegura($path) && Storage::disk('local')->exists($path), 404);
        $nombre = basename($politica['nombres_documentos'][$path] ?? $path);

        if ($request->boolean('inline')) {
            abort_unless(strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf' && Storage::disk('local')->mimeType($path) === 'application/pdf', 415);

            return Storage::disk('local')->response($path, $nombre, [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ], 'inline');
        }

        return Storage::disk('local')->download($path, $nombre);
    }

    public function indicadores(Request $request): mixed
    {
        if (is_string($request->query('i')) && $request->query('i') !== '') {
            $nuevo = SiidIndicador::query()->where('codigo', strtoupper(preg_replace('/\s+/', '', (string) $request->query('i'))))->first();
            if ($nuevo?->publicacion?->visible && $nuevo->activo) {
                return redirect()->route('micrositio.siid.show', $nuevo->publicacion, 301);
            }
        }

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

        if ($indicador['tabular_data_source_id'] && $indicador['tabular_data_source_version']) {
            $version = TabularDataSourceVersion::query()
                ->where('tabular_data_source_id', $indicador['tabular_data_source_id'])
                ->where('version', $indicador['tabular_data_source_version'])
                ->firstOrFail();
            $fields = collect($version->fields)->filter(fn (array $field): bool => ($field['visibility'] ?? 'public') === 'public')->values();

            return response()->streamDownload(function () use ($version, $fields): void {
                $salida = fopen('php://output', 'w');
                fputcsv($salida, $fields->pluck('label')->all());
                foreach ($version->records as $record) {
                    fputcsv($salida, $fields->map(fn (array $field): mixed => $record[$field['key']] ?? null)->all());
                }
                fclose($salida);
            }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

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

        abort_unless(is_string($path) && str_starts_with($path, 'micrositio/publicaciones/') && $this->contenido->rutaSegura($path) && Storage::disk('local')->exists($path), 404);
        $nombre = basename((string) $nombre);

        if ($request->boolean('inline')) {
            abort_unless(strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf' && Storage::disk('local')->mimeType($path) === 'application/pdf', 415);

            return Storage::disk('local')->response($path, $nombre, [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ], 'inline');
        }

        return Storage::disk('local')->download($path, $nombre);
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
