<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DataFieldVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTabularDataSourceRequest;
use App\Models\TabularDataSource;
use App\Services\AuditLogger;
use App\Services\Dashboards\TabularFileReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class TabularDataSourceController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-dashboards');

        return view('admin.data-sources.index', ['sources' => TabularDataSource::query()->with(['currentVersion', 'versions'])->orderBy('name')->get()]);
    }

    public function store(StoreTabularDataSourceRequest $request, TabularFileReader $reader, AuditLogger $audit): RedirectResponse
    {
        try {
            $parsed = $reader->read($request->file('file'));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
        $data = $request->validated();
        $source = DB::transaction(function () use ($data, $parsed, $request): TabularDataSource {
            $source = TabularDataSource::create(['name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?? null, 'created_by' => $request->user()->id, 'current_version' => 1]);
            $source->versions()->create(['version' => 1, 'original_filename' => $request->file('file')->getClientOriginalName(), 'checksum' => hash_file('sha256', $request->file('file')->getRealPath()), 'row_count' => count($parsed['records']), 'fields' => $parsed['fields'], 'records' => $parsed['records'], 'validation_summary' => $parsed['validation_summary'], 'created_by' => $request->user()->id]);

            return $source;
        });
        $audit->log(null, 'tabular_source_created', $request->user(), ['source_id' => $source->id], 'dashboards');

        return back()->with('status', 'Fuente importada. Revise la clasificación de sus campos.');
    }

    public function replace(Request $request, TabularDataSource $dataSource, TabularFileReader $reader): RedirectResponse
    {
        Gate::authorize('manage-dashboards');
        $request->validate(['file' => ['required', 'file', 'mimes:csv,xlsx', 'max:20480']]);
        try {
            $parsed = $reader->read($request->file('file'));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
        $previous = $dataSource->currentVersion;
        $previousVisibility = collect($previous?->fields ?? [])->keyBy('key')->map(fn (array $field): string => $field['visibility'] ?? 'analytics');
        $parsed['fields'] = collect($parsed['fields'])->map(function (array $field) use ($previousVisibility): array {
            $field['visibility'] = $previousVisibility->get($field['key'], 'analytics');

            return $field;
        })->all();
        DB::transaction(function () use ($dataSource, $parsed, $request): void {
            $version = $dataSource->current_version + 1;
            $dataSource->versions()->create(['version' => $version, 'original_filename' => $request->file('file')->getClientOriginalName(), 'checksum' => hash_file('sha256', $request->file('file')->getRealPath()), 'row_count' => count($parsed['records']), 'fields' => $parsed['fields'], 'records' => $parsed['records'], 'validation_summary' => $parsed['validation_summary'], 'created_by' => $request->user()->id]);
            $dataSource->update(['current_version' => $version]);
        });

        return back()->with('status', 'Nueva versión importada; el historial anterior se conserva.');
    }

    public function fields(Request $request, TabularDataSource $dataSource): RedirectResponse
    {
        Gate::authorize('manage-dashboards');
        $version = $dataSource->currentVersion()->firstOrFail();
        $request->validate(['fields' => ['required', 'array'], 'fields.*' => ['required', Rule::enum(DataFieldVisibility::class)]]);
        $visibility = $request->input('fields');
        $fields = collect($version->fields)->map(function (array $field) use ($visibility): array {
            $field['visibility'] = $visibility[$field['key']] ?? 'internal';

            return $field;
        })->all();
        $version->update(['fields' => $fields]);

        return back()->with('status', 'Clasificación de campos guardada.');
    }
}
