@extends('layouts.app', ['title' => ($project->exists ? 'Editar proyecto' : 'Nuevo proyecto').' · SIID 2.0'])

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <header>
        <p class="eyebrow">Seguimiento a metas</p>
        <h1 class="page-title">{{ $project->exists ? 'Editar proyecto' : 'Nuevo proyecto' }}</h1>
        <p class="page-subtitle">Complete los datos básicos y las dependencias responsables del proyecto.</p>
    </header>

    <form method="post" action="{{ $project->exists ? route('intelligence.proyectos-metas.update', $project) : route('intelligence.proyectos-metas.store') }}" class="panel space-y-6">
        @csrf
        @if($project->exists) @method('PATCH') @endif
        <div class="grid gap-5 md:grid-cols-2">
            <label class="field"><span>BPIN</span><input name="bpin" value="{{ old('bpin', $project->bpin) }}" required></label>
            <label class="field md:col-span-2"><span>Nombre del proyecto</span><textarea name="nombre" rows="3" required>{{ old('nombre', $project->nombre) }}</textarea></label>
            <label class="field"><span>Tipo de focalización</span><select name="tipo_focalizacion"><option value="">Sin definir</option>@foreach($focalizaciones as $value => $label)<option value="{{ $value }}" @selected(old('tipo_focalizacion', $project->tipo_focalizacion?->value) === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field"><span>Municipio</span><select name="municipio_id"><option value="">Sin municipio</option>@foreach($municipios as $municipio)<option value="{{ $municipio->id }}" @selected((string) old('municipio_id', $project->municipio_id) === (string) $municipio->id)>{{ $municipio->codigo_dane }} — {{ $municipio->nombre }}</option>@endforeach</select></label>
            <label class="field md:col-span-2"><span>Dependencias asociadas</span><select name="dependencia_ids[]" multiple size="8">@foreach($dependencias as $dependencia)<option value="{{ $dependencia->id }}" @selected($dependenciasSeleccionadas->contains($dependencia->id))>{{ $dependencia->codigo }} — {{ $dependencia->nombre }}</option>@endforeach</select><small>Use Ctrl/Cmd para seleccionar varias.</small></label>
            <label class="field md:col-span-2"><span>Metas producto asociadas</span><select name="meta_producto_ids[]" multiple size="8">@foreach($metasProducto as $metaProducto)<option value="{{ $metaProducto->id }}" @selected($metasSeleccionadas->contains($metaProducto->id))>{{ $metaProducto->codigo }} — {{ $metaProducto->nombre }}</option>@endforeach</select><small>Puede seleccionar varias; la relación también puede cargarse mediante la matriz masiva.</small></label>
            <label class="flex items-center gap-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="activo" value="1" @checked(old('activo', $project->exists ? $project->activo : true))> Proyecto activo</label>
        </div>
        @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="flex flex-wrap gap-3"><button class="btn-primary">Guardar proyecto</button><a class="btn-secondary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-proyectos']) }}">Cancelar</a></div>
    </form>
</div>
@endsection
