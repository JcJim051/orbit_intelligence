<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GeoLayerAccessPolicy;
use App\Enums\GeoViewerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeOpenDataSourceRequest;
use App\Http\Requests\StoreOpenDataSourceRequest;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use App\Models\OpenDataSource;
use App\Services\OpenData\AnalyzeDatosGovDataset;
use App\Services\OpenData\BuildOpenDataGeoJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OpenDataSourceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManageOpenDataSources() || $request->user()->canApproveSpatialPublication(), 403);
        $editableViewers = GeoViewer::query()
            ->where('status', '!=', GeoViewerStatus::Published->value)
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where(function ($query) use ($request): void {
                $query->where('owner_id', $request->user()->id)
                    ->orWhereHas('collaborators', fn ($query) => $query->whereKey($request->user()->id));
            }))
            ->orderBy('name')->get();

        return view('admin.open-data-sources.index', [
            'sources' => OpenDataSource::query()->with(['layer.viewers', 'owner'])->latest()->get(),
            'editableViewers' => $editableViewers,
            'canCreate' => $request->user()->canManageOpenDataSources(),
        ]);
    }

    public function analyze(AnalyzeOpenDataSourceRequest $request, AnalyzeDatosGovDataset $analyzer): JsonResponse
    {
        try {
            return response()->json($analyzer->handle($request->validated('url')));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function store(StoreOpenDataSourceRequest $request, AnalyzeDatosGovDataset $analyzer, BuildOpenDataGeoJson $builder): JsonResponse
    {
        $analysis = $analyzer->handle($request->validated('url'));
        $validated = $request->validated();
        $columns = collect($analysis['columns'])->keyBy('field');
        $selected = collect([...$validated['popup_fields'], ...$validated['filters']])->filter();
        if ($selected->contains(fn (string $field): bool => ! $columns->has($field))) {
            throw ValidationException::withMessages(['popup_fields' => 'Uno de los campos seleccionados ya no existe en Datos.gov.co.']);
        }
        if (($validated['metric_field'] ?? null) && ! collect($analysis['numeric_fields'])->pluck('field')->contains($validated['metric_field'])) {
            throw ValidationException::withMessages(['metric_field' => 'El indicador seleccionado no es numérico.']);
        }

        $viewer = DB::transaction(function () use ($request, $analysis, $validated, $columns, &$source): GeoViewer {
            if ($validated['target'] === 'existing') {
                $viewer = GeoViewer::findOrFail($validated['viewer_id']);
                abort_unless($viewer->canBeEditedBy($request->user()), 403);
            } else {
                $viewer = GeoViewer::create([
                    'owner_id' => $request->user()->id,
                    'name' => $validated['viewer_name'] ?: $validated['name'],
                    'slug' => $validated['viewer_slug'] ?: $validated['slug'].'-visor',
                    'description' => $validated['description'] ?? null,
                    'center_latitude' => 4.15,
                    'center_longitude' => -73.63,
                    'initial_zoom' => 8,
                    'status' => GeoViewerStatus::Draft,
                ]);
            }

            $style = [
                'color' => '#ffffff',
                'fillColor' => $this->palette($validated['palette'])[3],
                'weight' => 1.5,
                'radius' => 7,
                ...($analysis['geography']['mode'] === 'dane_municipality' ? ['choropleth' => [
                    'property' => 'valor',
                    'palette' => $this->palette($validated['palette']),
                    'noDataColor' => $validated['no_data_color'],
                ]] : []),
            ];
            $sourceFilters = collect($analysis['suggested_filters'])->whereIn('field', $validated['filters'])->values()->all();
            $layer = GeoLayer::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'group_name' => 'Datos abiertos',
                'source_type' => 'geojson',
                'source_url' => '/api/public/geodata/fuentes-abiertas/'.$validated['slug'],
                'geometry_type' => $analysis['geography']['mode'] === 'dane_municipality' ? 'polygon' : ($analysis['geography']['mode'] === 'coordinates' ? 'point' : 'mixed'),
                'popup_fields' => $analysis['geography']['mode'] === 'dane_municipality' ? ['municipio', 'valor', 'indicador', 'agregacion'] : $validated['popup_fields'],
                'public_attribute_fields' => $validated['popup_fields'],
                'style' => $style,
                'filters' => $this->layerFilters($sourceFilters),
                'attribution' => $validated['attribution'],
                'active' => true,
                'access_policy' => GeoLayerAccessPolicy::Downloadable,
                'download_url' => '/api/public/geodata/fuentes-abiertas/'.$validated['slug'],
                'download_format' => 'geojson',
                'is_open_data' => true,
                'source_page_url' => $analysis['landing_page_url'],
            ]);
            $source = OpenDataSource::create([
                'geo_layer_id' => $layer->id,
                'owner_id' => $request->user()->id,
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'dataset_id' => $analysis['dataset_id'],
                'landing_page_url' => $analysis['landing_page_url'],
                'metadata' => $analysis['metadata'],
                'schema_signature' => $analysis['schema_signature'],
                'geography_mode' => $analysis['geography']['mode'],
                'geometry_field' => $analysis['geography']['geometry_field'] ?? null,
                'latitude_field' => $analysis['geography']['latitude_field'] ?? null,
                'longitude_field' => $analysis['geography']['longitude_field'] ?? null,
                'dane_field' => $analysis['geography']['dane_field'] ?? null,
                'label_field' => $validated['label_field'] ?? null,
                'metric_field' => $validated['metric_field'] ?? null,
                'aggregation' => $validated['aggregation'],
                'filters' => $sourceFilters,
                'popup_fields' => $validated['popup_fields'],
                'style' => $style,
                'status' => 'draft',
            ]);
            $viewer->layers()->attach($layer->id, [
                'label' => $layer->name,
                'group_name' => 'Datos abiertos',
                'sort_order' => ($viewer->layers()->max('sort_order') ?? -1) + 1,
                'visible_by_default' => true,
                'show_in_legend' => true,
                'opacity' => (float) $validated['opacity'],
            ]);

            return $viewer;
        });

        try {
            $builder->handle($source, [], true);
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'message' => 'La fuente, la capa y el geovisor quedaron guardados como borrador.',
            'source' => $source->fresh(),
            'viewer_url' => route('admin.geo-viewers.preview', $viewer),
            'manage_url' => route('admin.open-data-sources.index'),
        ], 201);
    }

    public function update(Request $request, OpenDataSource $source, AnalyzeDatosGovDataset $analyzer): RedirectResponse
    {
        abort_unless($source->owner_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_if($source->layer->viewers()->whereIn('status', [GeoViewerStatus::Review->value, GeoViewerStatus::Published->value])->exists(), 409, 'La configuración enviada a revisión no puede modificarse. Cree un nuevo borrador.');
        $validated = $request->validate([
            'metric_field' => ['nullable', 'string', 'max:120'],
            'aggregation' => ['required', 'in:sum,avg,count,min,max'],
            'popup_fields' => ['array', 'max:12'],
            'popup_fields.*' => ['string', 'max:120', 'distinct'],
        ]);
        $analysis = $analyzer->handle($source->landing_page_url);
        $source->update([
            ...$validated,
            'metadata' => $analysis['metadata'],
            'schema_signature' => $analysis['schema_signature'],
            'status' => 'draft',
            'last_error' => null,
        ]);
        $source->layer->update(['popup_fields' => $validated['popup_fields'], 'public_attribute_fields' => $validated['popup_fields']]);

        return back()->with('status', 'Configuración actualizada. Revise la vista previa antes de enviarla a revisión.');
    }

    public function submit(Request $request, OpenDataSource $source): RedirectResponse
    {
        abort_unless($source->owner_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $viewers = $source->layer->viewers()->where('status', GeoViewerStatus::Draft->value)->get();
        abort_if($viewers->isEmpty(), 409, 'No hay un geovisor borrador asociado a esta fuente.');
        foreach ($viewers as $viewer) {
            abort_unless($viewer->canBeEditedBy($request->user()), 403);
            $viewer->update(['status' => GeoViewerStatus::Review]);
        }

        return back()->with('status', 'El geovisor fue enviado a revisión. Gerencia o administración podrán aprobar su publicación.');
    }

    /** @return array<int, string> */
    private function palette(string $name): array
    {
        return match ($name) {
            'blue' => ['#dbeafe', '#93c5fd', '#60a5fa', '#2563eb', '#1e3a8a'],
            'orange' => ['#ffedd5', '#fdba74', '#fb923c', '#ea580c', '#7c2d12'],
            'purple' => ['#f3e8ff', '#d8b4fe', '#c084fc', '#9333ea', '#581c87'],
            'red' => ['#fee2e2', '#fca5a5', '#f87171', '#dc2626', '#7f1d1d'],
            default => ['#dcfce7', '#86efac', '#4ade80', '#16a34a', '#166534'],
        };
    }

    /** @param array<int, array<string, mixed>> $filters */
    private function layerFilters(array $filters): array
    {
        return collect($filters)->map(fn (array $filter): array => [
            'name' => $filter['field'],
            'label' => $filter['label'],
            'default' => (string) ($filter['options'][0] ?? ''),
            'options' => collect($filter['options'] ?? [])->map(fn ($value): array => ['value' => (string) $value, 'label' => (string) $value])->all(),
        ])->all();
    }
}
