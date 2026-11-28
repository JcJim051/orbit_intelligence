<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPublicSpatialAnalysis;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\PublicSpatialAnalysisSession;
use App\Models\PublicToolPublication;
use App\Services\InteligenciaGeografica\InformePdf;
use App\Services\InteligenciaGeografica\LectorGeometria;
use App\Services\Micrositio\ContenidoMicrositio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PublicSpatialAnalysisController extends Controller
{
    public function index(ContenidoMicrositio $content): View
    {
        $tool = $this->activeTool();

        return view('micrositio.analisis-geoespacial', [
            'contenido' => $content->armar(false),
            'herramienta' => $tool,
            'capasCount' => DB::getDriverName() === 'pgsql' && Schema::hasTable('inteligencia.capa')
                ? Capa::query()->disponibleParaAnalisisPublico()->count()
                : 0,
        ]);
    }

    public function store(Request $request, LectorGeometria $reader): JsonResponse
    {
        $this->activeTool();
        $validated = $request->validate([
            'geojson' => ['nullable', 'string', 'max:2000000', 'required_without:archivo'],
            'archivo' => ['nullable', 'file', 'max:20480', 'required_without:geojson', 'extensions:kml,kmz,geojson,json,zip'],
        ]);

        if (PublicSpatialAnalysisSession::query()->where('ip_hash', $this->ipHash($request))->whereIn('estado', ['pendiente', 'procesando'])->exists()) {
            throw ValidationException::withMessages(['geojson' => 'Ya existe un análisis en proceso para esta conexión.']);
        }

        $file = $request->file('archivo');
        try {
            $geojson = $file
                ? $reader->desdeArchivo($file->getClientOriginalName(), $file->get())
                : $reader->desdeGeoJson((string) $validated['geojson']);
            $reader->afirmarCoordenadas($geojson, 4326);
            $this->assertVertexLimit($geojson);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'archivo' => 'No fue posible interpretar el área. Verifique el formato y que contenga una geometría poligonal válida.',
            ]);
        }

        $rawToken = Str::random(80);
        $origin = $file ? $this->originFromName($file->getClientOriginalName()) : 'dibujo';
        $session = PublicSpatialAnalysisSession::query()->create([
            'token_hash' => hash('sha256', $rawToken),
            'ip_hash' => $this->ipHash($request),
            'estado' => 'pendiente',
            'origen_geometria' => $origin,
            'nombre_archivo' => $file?->getClientOriginalName(),
            'geojson' => $geojson,
            'expires_at' => now()->addDay(),
        ]);
        ProcessPublicSpatialAnalysis::dispatch($session->id);

        return response()->json([
            'token' => $rawToken,
            'status_url' => route('micrositio.analisis-geoespacial.status', $rawToken),
        ], 202);
    }

    public function status(string $token): JsonResponse
    {
        $session = $this->session($token);
        $analysis = $session->estado === 'listo'
            ? $session->analysis?->load(['resultados.capa.fuente'])
            : null;

        return response()->json([
            'estado' => $session->estado,
            'error' => $session->error_publico,
            'area_hectareas' => $analysis?->areaHectareas(),
            'capas_analizadas' => $analysis?->resultados()->count(),
            'resultados' => $analysis?->resultados->map(fn ($result) => [
                'capa' => $result->capa?->nombre ?? 'Capa oficial',
                'conteo' => $result->conteo,
                'area_hectareas' => $result->area_m2 !== null ? round((float) $result->area_m2 / 10000, 4) : null,
                'longitud_km' => $result->longitud_m !== null ? round((float) $result->longitud_m / 1000, 4) : null,
                'fuente' => $result->cita_fuente ?: $result->capa?->cita_fuente ?: $result->capa?->fuente?->nombre,
                'fecha_corte' => $result->fecha_corte?->format('d/m/Y'),
                'licencia' => $result->licencia,
                'advertencia' => $result->obsoleto ? 'La copia disponible superó su vigencia esperada.' : null,
            ])->values(),
            'pdf_url' => $analysis ? route('micrositio.analisis-geoespacial.pdf', $token) : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function pdf(string $token, InformePdf $report): Response
    {
        $session = $this->session($token);
        abort_unless($session->estado === 'listo' && $session->analysis, 404);

        return response($report->pdf($session->analysis), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="informe-analisis-geoespacial.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function activeTool(): PublicToolPublication
    {
        $tool = PublicToolPublication::query()->where('codigo', PublicToolPublication::GEOSPATIAL_ANALYSIS)->first();
        abort_unless($tool?->visible, 404);

        return $tool;
    }

    private function session(string $token): PublicSpatialAnalysisSession
    {
        $session = PublicSpatialAnalysisSession::fromToken($token);
        abort_if($session === null || $session->isExpired(), 404);

        return $session;
    }

    private function ipHash(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
    }

    private function originFromName(string $name): string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'kml' => 'kml', 'kmz' => 'kmz', 'zip' => 'shapefile', default => 'geojson',
        };
    }

    private function assertVertexLimit(string $geojson): void
    {
        $decoded = json_decode($geojson, true, flags: JSON_THROW_ON_ERROR);
        $count = 0;
        $walk = function (mixed $value) use (&$walk, &$count): void {
            if (! is_array($value)) {
                return;
            }
            if (count($value) >= 2 && is_numeric($value[0] ?? null) && is_numeric($value[1] ?? null)) {
                $count++;

                return;
            }
            foreach ($value as $child) {
                $walk($child);
            }
        };
        $walk($decoded['coordinates'] ?? $decoded);
        if ($count > 50000) {
            throw ValidationException::withMessages(['geojson' => 'El área supera el máximo de 50.000 vértices.']);
        }
    }
}
