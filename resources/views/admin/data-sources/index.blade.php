@extends('layouts.app', ['title' => 'Fuentes tabulares · SIID 2.0'])

@section('content')
<div class="space-y-7">
    <header><p class="eyebrow">Datos para análisis</p><h1 class="page-title">Fuentes tabulares</h1><p class="page-subtitle">Importe CSV o XLSX, conserve sus versiones y decida qué campos pueden publicarse.</p></header>
    <details class="panel action-disclosure" @if($errors->any()) open @endif><summary><div><h2>Importar una fuente</h2><span>Máximo 10.000 filas y 20 MB por versión.</span></div><strong>Abrir formulario</strong></summary>
        <form method="post" enctype="multipart/form-data" action="{{ route('admin.data-sources.store') }}" class="mt-5 grid gap-4 md:grid-cols-2" data-slug-suggestion>@csrf
            <label class="field"><span>Nombre</span><input name="name" required data-slug-source></label><label class="field"><span>Identificador</span><input name="slug" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" data-slug-target></label>
            <label class="field md:col-span-2"><span>Descripción</span><textarea name="description"></textarea></label><label class="field"><span>Archivo CSV o XLSX</span><input type="file" name="file" accept=".csv,.xlsx" required></label><button class="btn-primary self-end">Importar y revisar campos</button>
        </form>
    </details>
    <div class="space-y-5">@forelse($sources as $source)
        <details class="panel"><summary class="cursor-pointer"><div class="flex items-center justify-between gap-4"><div><h2 class="text-lg font-semibold">{{ $source->name }}</h2><p class="text-sm text-slate-500">Versión {{ $source->current_version }} · {{ number_format($source->currentVersion?->row_count ?? 0, 0, ',', '.') }} filas</p></div><span class="status">{{ $source->versions->count() }} versiones</span></div></summary>
            @if($source->currentVersion)
            @if(data_get($source->currentVersion->validation_summary, 'population_mismatch_count', 0) > 0)<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Se detectaron {{ data_get($source->currentVersion->validation_summary, 'population_mismatch_count') }} diferencias entre los totales poblacionales y sus componentes. Los datos no fueron modificados.</div>@endif
            <form method="post" action="{{ route('admin.data-sources.fields.update', $source) }}" class="mt-5">@csrf @method('PUT')<h3 class="font-semibold">Clasificación de campos</h3><p class="mb-4 text-sm text-slate-500">Los campos internos nunca salen por la API pública.</p>
                <div class="grid gap-3 md:grid-cols-2">@foreach($source->currentVersion->fields as $field)<label class="field rounded-xl border border-slate-200 p-3"><span>{{ $field['label'] }} <small class="font-mono text-slate-400">{{ $field['key'] }} · {{ $field['type'] }}</small></span><select name="fields[{{ $field['key'] }}]">@foreach(App\Enums\DataFieldVisibility::cases() as $visibility)<option value="{{ $visibility->value }}" @selected(($field['visibility'] ?? 'analytics') === $visibility->value)>{{ $visibility->label() }}</option>@endforeach</select></label>@endforeach</div><button class="btn-primary mt-4">Guardar clasificación</button>
            </form>
            <form method="post" enctype="multipart/form-data" action="{{ route('admin.data-sources.versions.store', $source) }}" class="mt-6 flex flex-wrap items-end gap-3">@csrf<label class="field min-w-72 flex-1"><span>Reemplazar con una nueva versión</span><input type="file" name="file" accept=".csv,.xlsx" required></label><button class="btn-secondary">Importar nueva versión</button></form>
            @endif
        </details>
    @empty <div class="panel text-sm text-slate-500">Aún no hay fuentes tabulares.</div>@endforelse</div>
</div>
@endsection
