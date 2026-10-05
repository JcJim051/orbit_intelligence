<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IndicatorStatus;
use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\Indicator;
use App\Models\TabularDataSource;
use App\Services\Dashboards\TabularFileReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class IndicatorController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-dashboards');

        $indicators = Indicator::query()
            ->with(['owner', 'approver', 'tabularDataSource.currentVersion'])
            ->when(
                ! $request->user()->canApproveDashboards(),
                fn ($query) => $query->where('owner_id', $request->user()->id)
            )
            ->orderBy('name')
            ->get();

        $tabularSources = TabularDataSource::query()->with('currentVersion')->orderBy('name')->get();

        return view('admin.indicators.index', compact('indicators', 'tabularSources'));
    }

    public function store(Request $request, TabularFileReader $reader): RedirectResponse
    {
        Gate::authorize('manage-dashboards');

        abort_if($request->input('data_series_mode') === 'upload' && ! $request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'alpha_dash:ascii', 'unique:indicators,slug'],
            'summary' => ['nullable', 'string', 'max:700'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sector' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:80'],
            'periodicity' => ['nullable', 'string', 'max:80'],
            'technical_sheet' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'data_series_mode' => ['required', 'in:upload,existing'],
            'data_file' => ['nullable', 'file', 'mimes:csv,xlsx', 'max:20480'],
            'tabular_data_source_id' => ['nullable', 'exists:tabular_data_sources,id'],
            'scope_field' => ['nullable', 'string', 'max:120'],
            'scope_values' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['data_series_mode'] === 'upload' && ! $request->hasFile('data_file')) {
            throw ValidationException::withMessages(['data_file' => 'Cargue la serie de datos en CSV o XLSX.']);
        }
        if ($data['data_series_mode'] === 'existing' && blank($data['tabular_data_source_id'] ?? null)) {
            throw ValidationException::withMessages(['tabular_data_source_id' => 'Seleccione una fuente tabular existente.']);
        }

        $file = $request->file('technical_sheet');
        $path = $file->store("indicators/{$data['slug']}/technical-sheets", 'local');
        $source = $data['data_series_mode'] === 'upload'
            ? $this->createSeriesSource($request, $reader, $data)
            : TabularDataSource::query()->findOrFail($data['tabular_data_source_id']);

        Indicator::create([
            ...collect($data)->except(['technical_sheet', 'data_file', 'data_series_mode', 'tabular_data_source_id', 'scope_field', 'scope_values'])->all(),
            'owner_id' => $request->user()->id,
            'status' => IndicatorStatus::Draft,
            'source_type' => 'tabular',
            'tabular_data_source_id' => $source->id,
            'data_scope_config' => $this->scopeConfig($data),
            'technical_sheet_disk' => 'local',
            'technical_sheet_path' => $path,
            'technical_sheet_original_name' => $file->getClientOriginalName(),
            'technical_sheet_mime' => $file->getMimeType() ?: 'application/pdf',
            'technical_sheet_size' => $file->getSize(),
        ]);

        return redirect(Workspace::getUrl(['workspace' => 'indicadores']))
            ->with('status', 'Indicador creado como borrador. Revise la ficha y envíelo a revisión cuando esté listo.');
    }

    public function update(Request $request, Indicator $indicator, TabularFileReader $reader): RedirectResponse
    {
        abort_unless($indicator->canEdit($request->user()), 403);

        abort_if($request->input('data_series_mode') === 'upload' && ! $request->user()->isAdmin(), 403);

        if ($indicator->isPublished()) {
            return back()->with('error', 'Este indicador ya está publicado. Para modificarlo se debe crear un nuevo borrador de versión.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'alpha_dash:ascii', Rule::unique('indicators', 'slug')->ignore($indicator->id)],
            'summary' => ['nullable', 'string', 'max:700'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sector' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:80'],
            'periodicity' => ['nullable', 'string', 'max:80'],
            'technical_sheet' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'data_series_mode' => ['nullable', 'in:keep,upload,existing'],
            'data_file' => ['nullable', 'file', 'mimes:csv,xlsx', 'max:20480'],
            'tabular_data_source_id' => ['nullable', 'exists:tabular_data_sources,id'],
            'scope_field' => ['nullable', 'string', 'max:120'],
            'scope_values' => ['nullable', 'string', 'max:2000'],
        ]);

        $attributes = collect($data)->except(['technical_sheet', 'data_file', 'data_series_mode', 'tabular_data_source_id', 'scope_field', 'scope_values'])->all();
        if (array_key_exists('scope_field', $data) || array_key_exists('scope_values', $data)) {
            $attributes['data_scope_config'] = $this->scopeConfig($data);
        }

        if ($request->hasFile('technical_sheet')) {
            $file = $request->file('technical_sheet');
            if ($indicator->technical_sheet_path) {
                Storage::disk($indicator->technical_sheet_disk ?: 'local')->delete($indicator->technical_sheet_path);
            }
            $path = $file->store("indicators/{$data['slug']}/technical-sheets", 'local');
            $attributes += [
                'technical_sheet_disk' => 'local',
                'technical_sheet_path' => $path,
                'technical_sheet_original_name' => $file->getClientOriginalName(),
                'technical_sheet_mime' => $file->getMimeType() ?: 'application/pdf',
                'technical_sheet_size' => $file->getSize(),
            ];
        }

        if (($data['data_series_mode'] ?? 'keep') === 'upload') {
            if (! $request->hasFile('data_file')) {
                throw ValidationException::withMessages(['data_file' => 'Cargue la serie de datos en CSV o XLSX.']);
            }
            $source = $indicator->tabularDataSource
                ? $this->replaceSeriesSource($indicator->tabularDataSource, $request, $reader)
                : $this->createSeriesSource($request, $reader, $data);
            $attributes['source_type'] = 'tabular';
            $attributes['tabular_data_source_id'] = $source->id;
        }

        if (($data['data_series_mode'] ?? 'keep') === 'existing') {
            if (blank($data['tabular_data_source_id'] ?? null)) {
                throw ValidationException::withMessages(['tabular_data_source_id' => 'Seleccione una fuente tabular existente.']);
            }
            $attributes['source_type'] = 'tabular';
            $attributes['tabular_data_source_id'] = $data['tabular_data_source_id'];
        }

        $indicator->update([
            ...$attributes,
            'status' => IndicatorStatus::Draft,
            'submitted_at' => null,
        ]);

        return redirect(Workspace::getUrl(['workspace' => 'indicadores']))
            ->with('status', 'Indicador actualizado. La edición vuelve a quedar como borrador.');
    }

    public function submit(Request $request, Indicator $indicator): RedirectResponse
    {
        abort_unless($indicator->canEdit($request->user()), 403);

        if (! $indicator->hasTechnicalSheet()) {
            return back()->with('error', 'Cargue la ficha técnica oficial en PDF antes de enviar a revisión.');
        }
        if (! $indicator->hasDataSeries()) {
            return back()->with('error', 'Cargue o seleccione la serie de datos antes de enviar a revisión.');
        }

        $indicator->update([
            'status' => IndicatorStatus::PendingReview,
            'submitted_at' => now(),
        ]);

        return back()->with('status', 'Indicador enviado a revisión.');
    }

    /** @param array<string, mixed> $data */
    private function createSeriesSource(Request $request, TabularFileReader $reader, array $data): TabularDataSource
    {
        $parsed = $this->applyScope($this->readSeries($request, $reader), $this->scopeConfig($data));
        $uploaded = $request->file('data_file');
        $slug = $this->uniqueSourceSlug((string) ($data['slug'] ?? str($data['name'] ?? 'indicador')->slug()));

        return DB::transaction(function () use ($data, $parsed, $request, $uploaded, $slug): TabularDataSource {
            $source = TabularDataSource::create([
                'name' => 'Serie de datos · '.$data['name'],
                'slug' => $slug,
                'description' => 'Serie de datos asociada al indicador '.$data['name'].'.',
                'created_by' => $request->user()->id,
                'current_version' => 1,
            ]);
            $source->versions()->create([
                'version' => 1,
                'original_filename' => $uploaded->getClientOriginalName(),
                'checksum' => hash_file('sha256', $uploaded->getRealPath()),
                'row_count' => count($parsed['records']),
                'fields' => $parsed['fields'],
                'records' => $parsed['records'],
                'validation_summary' => $parsed['validation_summary'],
                'created_by' => $request->user()->id,
            ]);

            return $source;
        });
    }

    private function replaceSeriesSource(TabularDataSource $source, Request $request, TabularFileReader $reader): TabularDataSource
    {
        $scope = $this->scopeConfig($request->all());
        $parsed = $this->applyScope($this->readSeries($request, $reader), $scope ?: ($request->route('indicator')?->data_scope_config ?? []));
        $uploaded = $request->file('data_file');
        $previousVisibility = collect($source->currentVersion?->fields ?? [])
            ->keyBy('key')
            ->map(fn (array $field): string => $field['visibility'] ?? 'analytics');
        $parsed['fields'] = collect($parsed['fields'])->map(function (array $field) use ($previousVisibility): array {
            $field['visibility'] = $previousVisibility->get($field['key'], $field['visibility'] ?? 'analytics');

            return $field;
        })->all();

        DB::transaction(function () use ($source, $parsed, $request, $uploaded): void {
            $version = $source->current_version + 1;
            $source->versions()->create([
                'version' => $version,
                'original_filename' => $uploaded->getClientOriginalName(),
                'checksum' => hash_file('sha256', $uploaded->getRealPath()),
                'row_count' => count($parsed['records']),
                'fields' => $parsed['fields'],
                'records' => $parsed['records'],
                'validation_summary' => $parsed['validation_summary'],
                'created_by' => $request->user()->id,
            ]);
            $source->update(['current_version' => $version]);
        });

        return $source->refresh();
    }

    /** @return array{fields: array<int, array<string, mixed>>, records: array<int, array<string, mixed>>, validation_summary: array<string, mixed>} */
    private function readSeries(Request $request, TabularFileReader $reader): array
    {
        try {
            return $reader->read($request->file('data_file'));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['data_file' => $exception->getMessage()]);
        }
    }

    private function uniqueSourceSlug(string $slug): string
    {
        $base = str($slug)->slug()->prepend('indicador-')->toString();
        $candidate = $base;
        $suffix = 2;
        while (TabularDataSource::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    /** @param array<string, mixed> $data
     * @return array{field: string|null, values: array<int, string>}
     */
    private function scopeConfig(array $data): array
    {
        $field = trim((string) ($data['scope_field'] ?? ''));
        $values = collect(preg_split('/[\r\n,;]+/', (string) ($data['scope_values'] ?? '')) ?: [])
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->values()
            ->all();

        return [
            'field' => $field !== '' ? $field : null,
            'values' => $values,
        ];
    }

    /** @param array{fields: array<int, array<string, mixed>>, records: array<int, array<string, mixed>>, validation_summary: array<string, mixed>} $parsed
     * @param  array{field?: string|null, values?: array<int, string>}  $scope
     * @return array{fields: array<int, array<string, mixed>>, records: array<int, array<string, mixed>>, validation_summary: array<string, mixed>}
     */
    private function applyScope(array $parsed, array $scope): array
    {
        $field = $scope['field'] ?? null;
        $values = collect($scope['values'] ?? [])->map(fn (string $value): string => mb_strtolower(trim($value)))->filter()->values();

        if (! $field || $values->isEmpty()) {
            return $parsed;
        }

        $fieldExists = collect($parsed['fields'])->contains(fn (array $candidate): bool => ($candidate['key'] ?? null) === $field);
        if (! $fieldExists) {
            throw ValidationException::withMessages(['scope_field' => "El campo de alcance «{$field}» no existe en la serie cargada. Use el identificador interno del campo."]);
        }

        $records = collect($parsed['records'])
            ->filter(fn (array $row): bool => $values->contains(mb_strtolower(trim((string) ($row[$field] ?? '')))))
            ->values()
            ->all();

        if ($records === []) {
            throw ValidationException::withMessages(['scope_values' => 'El filtro de alcance no dejó registros. Revise el campo y los valores permitidos.']);
        }

        $parsed['records'] = $records;
        $parsed['validation_summary']['scope'] = [
            'field' => $field,
            'values' => $scope['values'],
            'stored_rows' => count($records),
        ];

        return $parsed;
    }
}
