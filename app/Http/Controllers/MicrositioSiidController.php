<?php

namespace App\Http\Controllers;

use App\Models\IndicadorTerritorial;
use App\Models\SiidEje;
use App\Models\SiidIndicador;
use App\Models\SiidPublicacion;
use App\Services\Micrositio\ContenidoMicrositio;
use App\Services\Siid\TableroEmbedValidator;
use App\Services\Siid\TerritorialPublicSeries;
use App\Services\Superset\SupersetClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MicrositioSiidController extends Controller
{
    public function __construct(
        private readonly ContenidoMicrositio $contenido,
        private readonly TableroEmbedValidator $validator,
        private readonly TerritorialPublicSeries $territorialSeries,
    ) {}

    public function index(Request $request): mixed
    {
        if (filled($request->query('i'))) {
            $indicator = SiidIndicador::query()
                ->where('codigo', strtoupper(preg_replace('/\s+/', '', (string) $request->query('i'))))
                ->first();
            if ($indicator?->publicacion?->visible && $indicator->activo) {
                return redirect()->route('micrositio.siid.show', $indicator->publicacion);
            }
        }

        $tipo = in_array($request->query('tipo'), ['siid', 'territorial'], true) ? (string) $request->query('tipo') : '';
        $eje = in_array($request->query('eje'), ['SOCIODEMOGRAFICO', 'ECONOMICO', 'AMBIENTAL_TERRITORIAL'], true)
            ? (string) $request->query('eje')
            : '';
        $search = trim((string) $request->query('q'));
        $areasTerritoriales = [
            'SOCIODEMOGRAFICO' => 'SOCIODEMOGRÁFICA',
            'ECONOMICO' => 'ECONÓMICA',
            'AMBIENTAL_TERRITORIAL' => 'AMBIENTAL',
        ];

        $consulta = SiidPublicacion::query()
            ->publicas()
            ->with(['publicable' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([
                    SiidIndicador::class => ['eje', 'area'],
                    IndicadorTerritorial::class => ['dependencia'],
                ]);
            }])
            ->whereHasMorph('publicable', '*', fn (Builder $publicable): Builder => $publicable->where('activo', true));

        if ($tipo !== '') {
            $consulta->where('publicable_type', $tipo === 'siid' ? SiidIndicador::class : IndicadorTerritorial::class);
        }

        if ($eje !== '') {
            $consulta->whereHasMorph('publicable', '*', function (Builder $publicable, string $modelo) use ($eje, $areasTerritoriales): Builder {
                if ($modelo === SiidIndicador::class) {
                    return $publicable->whereHas('eje', fn (Builder $ejeQuery): Builder => $ejeQuery->where('codigo', $eje));
                }

                if ($modelo === IndicadorTerritorial::class) {
                    return $publicable->whereRaw('UPPER(area_tematica) = ?', [$areasTerritoriales[$eje]]);
                }

                return $publicable->whereRaw('1 = 0');
            });
        }

        if ($search !== '') {
            $patron = '%'.mb_strtolower($search).'%';
            $consulta->where(function (Builder $query) use ($patron): void {
                $query->whereRaw("LOWER(COALESCE(etiqueta, '')) LIKE ?", [$patron])
                    ->orWhereHasMorph('publicable', '*', fn (Builder $publicable): Builder => $publicable
                        ->whereRaw('LOWER(codigo) LIKE ?', [$patron])
                        ->orWhereRaw('LOWER(nombre) LIKE ?', [$patron]));
            });
        }

        $publicaciones = $consulta->paginate(12)->withQueryString();

        return view('micrositio.siid.index', [
            'contenido' => $this->contenido->armar(false),
            'ejes' => SiidEje::query()->where('activo', true)->orderBy('orden')->get(),
            'publicaciones' => $publicaciones,
            'filtros' => ['q' => $search, 'tipo' => $tipo, 'eje' => $eje],
        ]);
    }

    public function show(SiidPublicacion $publicacion): mixed
    {
        $publicacion->loadMorph('publicable', [
            SiidIndicador::class => ['eje', 'area'],
            IndicadorTerritorial::class => ['dependencia'],
        ]);
        abort_unless($publicacion->visible && $publicacion->publicableIsActive(), 404);
        [$type, $reference] = $this->dashboard($publicacion);
        $territorialSeries = $publicacion->publicable instanceof IndicadorTerritorial && $publicacion->mostrar_serie
            ? $this->territorialSeries->build($publicacion->publicable)
            : [];

        return view('micrositio.siid.show', [
            'contenido' => $this->contenido->armar(false),
            'publicacion' => $publicacion,
            'indicador' => $publicacion->publicable,
            'tableroTipo' => $type,
            'tableroReferencia' => $reference,
            'lookerValido' => $type === 'looker' && $this->validator->looker($reference),
            'supersetValido' => $type === 'superset' && $this->validator->superset($reference),
            'descripcionPublica' => $publicacion->descripcion_publica ?: ($publicacion->publicable->descripcion ?? $publicacion->publicable->definicion ?? null),
            'fuentePublica' => $publicacion->fuente_publica ?: ($publicacion->publicable->fuente ?? $publicacion->publicable->fuente_informacion ?? null),
            'periodicidadPublica' => $publicacion->periodicidad_publica ?: ($publicacion->publicable->periodicidad ?? null),
            'imagenUrl' => $publicacion->imageUrl(),
            'territorialSeries' => $territorialSeries,
        ]);
    }

    public function token(SiidPublicacion $publicacion, SupersetClient $superset): JsonResponse
    {
        $publicacion->load('publicable');
        [$type, $reference] = $this->dashboard($publicacion);
        abort_unless($publicacion->visible && $publicacion->publicableIsActive() && $type === 'superset' && $this->validator->superset($reference), 403);

        try {
            $token = $superset->tokenInvitado((string) $reference, [], true);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json(['token' => $token, 'uuid' => $reference, 'superset_url' => config('superset.url'), 'rls' => []]);
    }

    public function legacy(string $codigo): mixed
    {
        $indicator = SiidIndicador::query()->where('codigo', strtoupper(preg_replace('/\s+/', '', $codigo)))->first();
        $publication = $indicator?->publicacion;
        abort_unless($publication, 404);

        return redirect()->route('micrositio.siid.show', $publication, 301);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function dashboard(SiidPublicacion $publication): array
    {
        $item = $publication->publicable;
        $type = $publication->tablero_tipo ?: ($item->tipo_tablero ?? $item->tablero_tipo ?? null);
        $reference = $publication->tablero_referencia ?: ($item->url_tablero ?? $item->tablero_referencia ?? null);
        if (! $reference && $item instanceof SiidIndicador) {
            $reference = $item->eje?->tablero_referencia;
            $type = $item->eje?->tablero_tipo;
        }

        return [$type, $reference];
    }
}
