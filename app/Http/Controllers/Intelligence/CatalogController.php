<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Dependencia;
use App\Services\Intelligence\CatalogImporter;
use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\CatalogWorkbook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class CatalogController extends Controller
{
    abstract protected function catalog(): CatalogDefinition;

    public function index(Request $request): View
    {
        $catalog = $this->catalog();
        $this->authorize('viewAny', $catalog->modelClass());

        $query = $catalog->newQuery();
        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
            $catalog->applySearch($query, $search);
        }

        if (in_array($request->query('activo'), ['0', '1'], true)) {
            $query->where('activo', $request->query('activo') === '1');
        }

        foreach (['tipo', 'tipo_regla'] as $filter) {
            $field = $catalog->fieldByColumn($filter);

            if ($field === null || ! in_array($filter, $catalog->filters(), true) || ! $request->filled($filter)) {
                continue;
            }

            $value = (string) $request->query($filter);

            if (! array_key_exists($value, $field->options ?? [])) {
                continue;
            }

            $query->where($filter, $value);
        }

        $catalog->order($query);

        return view('intelligence.catalogs.index', [
            'catalog' => $catalog,
            'records' => $query->paginate(20)->withQueryString(),
            'filters' => [
                'q' => $search,
                'activo' => (string) $request->query('activo', ''),
                'tipo' => (string) $request->query('tipo', ''),
                'tipo_regla' => (string) $request->query('tipo_regla', ''),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', $this->catalog()->modelClass());

        return view('intelligence.catalogs.form', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $catalog = $this->catalog();
        $this->authorize('create', $catalog->modelClass());

        $normalized = $catalog->normalize($request->all(), excel: false);
        $validated = $catalog->makeValidator($normalized, excel: false)->validate();
        $catalog->modelClass()::query()->create($catalog->attributesFrom($validated, excel: false));

        return redirect()
            ->route($catalog->routeName().'.index')
            ->with('status', 'El registro se creó.');
    }

    public function edit(Request $request): View
    {
        $record = $this->record($request);
        $this->authorize('update', $record);

        return view('intelligence.catalogs.form', $this->formData($request, $record));
    }

    public function update(Request $request): RedirectResponse
    {
        $catalog = $this->catalog();
        $record = $this->record($request);
        $this->authorize('update', $record);

        $normalized = $catalog->normalize($request->all(), excel: false);
        $validated = $catalog->makeValidator($normalized, excel: false, ignoreId: (int) $record->getKey())->validate();
        $record->update($catalog->attributesFrom($validated, excel: false));

        return redirect()
            ->route($catalog->routeName().'.index')
            ->with('status', 'El registro se actualizó.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $catalog = $this->catalog();
        $record = $this->record($request);
        $this->authorize('delete', $record);

        if ($catalog->usesSoftDeletes()) {
            $record->delete();
            $message = 'El registro se eliminó.';
        } else {
            $record->update(['activo' => false]);
            $message = 'El registro quedó inactivo.';
        }

        return redirect()
            ->route($catalog->routeName().'.index')
            ->with('status', $message);
    }

    public function export(CatalogWorkbook $workbook): BinaryFileResponse
    {
        $catalog = $this->catalog();
        $this->authorize('export', $catalog->modelClass());
        $path = $this->temporaryPath();
        $workbook->writeExport($catalog, $path);

        return response()->download($path, $catalog->exportFilename())->deleteFileAfterSend(true);
    }

    public function template(CatalogWorkbook $workbook): BinaryFileResponse
    {
        $catalog = $this->catalog();
        $this->authorize('import', $catalog->modelClass());
        $path = $this->temporaryPath();
        $workbook->writeTemplate($catalog, $path);

        return response()->download($path, $catalog->templateFilename())->deleteFileAfterSend(true);
    }

    public function import(Request $request, CatalogImporter $importer): RedirectResponse
    {
        $catalog = $this->catalog();
        $this->authorize('import', $catalog->modelClass());

        $request->validate([
            'archivo' => ['required', 'file', 'extensions:xlsx', 'max:5120'],
        ], [
            'archivo.required' => 'Seleccione un archivo de Excel.',
            'archivo.extensions' => 'El archivo debe ser un libro .xlsx.',
            'archivo.max' => 'El archivo no puede superar 5 MB.',
        ]);

        $result = $importer->import($catalog, $request->file('archivo')->getPathname());

        if (! $result->applied) {
            $errors = [
                'importacion' => 'No se importó ninguna fila. Corrija el archivo y vuelva a cargarlo.',
            ];

            foreach ($result->errors as $index => $error) {
                $errors['fila_'.$index] = $error;
            }

            return back()->withErrors($errors);
        }

        return back()->with('status', $result->message());
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request, ?Model $record = null): array
    {
        $needsDependency = $this->catalog()->fieldByColumn('dependencia_id') !== null;

        return [
            'catalog' => $this->catalog(),
            'record' => $record,
            'dependencias' => $needsDependency
                ? Dependencia::query()->orderBy('codigo')->get()
                : collect(),
        ];
    }

    private function record(Request $request): Model
    {
        $catalog = $this->catalog();
        $model = $catalog->modelClass();

        return $model::query()->findOrFail($request->route($catalog->routeParameter()));
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog');

        if ($path === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal.');
        }

        return $path;
    }
}
