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
            <label class="field"><span>Tipo de regionalización territorial</span><select id="tipo-focalizacion" name="tipo_focalizacion"><option value="">Sin definir</option>@foreach($focalizaciones as $value => $label)<option value="{{ $value }}" @selected(old('tipo_focalizacion', $project->tipo_focalizacion?->value) === $value)>{{ $label }}</option>@endforeach</select><small>Define si el proyecto se ejecuta en un municipio, varios municipios o todo el departamento.</small></label>
            <label class="field"><span>Regionalización del reporte</span><select id="regionalizacion-modo" name="regionalizacion_modo">@foreach(App\Models\Proyecto::modosRegionalizacion() as $value => $label)<option value="{{ $value }}" @selected(old('regionalizacion_modo', $project->regionalizacion_modo ?? App\Models\Proyecto::REGIONALIZACION_PROYECTO) === $value)>{{ $label }}</option>@endforeach</select><small>Define si el comprometido se distribuye por todo el proyecto o solo por metas seleccionadas.</small></label>
            <label id="municipio-unico-field" class="field"><span>Municipio principal</span><select name="municipio_id"><option value="">Sin municipio</option>@foreach($municipios as $municipio)<option value="{{ $municipio->id }}" @selected((string) old('municipio_id', $project->municipio_id) === (string) $municipio->id)>{{ $municipio->codigo_dane }} — {{ $municipio->nombre }}</option>@endforeach</select></label>
            <label id="municipios-regionalizacion-field" class="field md:col-span-2"><span>Municipios de regionalización</span><select id="municipios-regionalizacion" name="municipio_ids[]" multiple size="8">@foreach($municipios as $municipio)<option value="{{ $municipio->id }}" @selected(collect(old('municipio_ids', $municipiosSeleccionados->all()))->contains($municipio->id))>{{ $municipio->codigo_dane }} — {{ $municipio->nombre }}</option>@endforeach</select><span class="mt-2 flex flex-wrap gap-2"><button type="button" class="btn-small" data-municipios-todos>Seleccionar todos</button><button type="button" class="btn-small" data-municipios-excepto-villavicencio>Todos excepto Villavicencio</button><button type="button" class="btn-small" data-municipios-limpiar>Limpiar</button></span><small>Para proyectos multimunicipio seleccione los municipios. En proyectos departamentales puede seleccionar todos y retirar los que no apliquen.</small></label>
            <label class="field md:col-span-2"><span>Dependencias asociadas</span><select name="dependencia_ids[]" multiple size="8">@foreach($dependencias as $dependencia)<option value="{{ $dependencia->id }}" @selected($dependenciasSeleccionadas->contains($dependencia->id))>{{ $dependencia->codigo }} — {{ $dependencia->nombre }}</option>@endforeach</select><small>Use Ctrl/Cmd para seleccionar varias.</small></label>
            <label class="field md:col-span-2"><span>Metas producto asociadas</span><select id="metas-producto-asociadas" name="meta_producto_ids[]" multiple size="8">@foreach($metasProducto as $metaProducto)<option value="{{ $metaProducto->id }}" @selected($metasSeleccionadas->contains($metaProducto->id))>{{ $metaProducto->codigo }} — {{ $metaProducto->nombre }}</option>@endforeach</select><small>Puede seleccionar varias; la relación también puede cargarse mediante la matriz masiva.</small></label>
            <fieldset id="metas-regionalizacion-field" class="field md:col-span-2"><legend>Metas producto que se regionalizarán</legend><div id="metas-regionalizacion-list" class="grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-2">@foreach($metasProducto->whereIn('id', $metasSeleccionadas) as $metaProducto)<label class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white p-2 text-sm"><input class="!mt-1 !w-auto shrink-0" type="checkbox" name="regionalizacion_meta_ids[]" value="{{ $metaProducto->id }}" @checked(collect(old('regionalizacion_meta_ids', $metasRegionalizacionSeleccionadas->all()))->contains($metaProducto->id))><span>{{ $metaProducto->codigo }} — {{ $metaProducto->nombre }}</span></label>@endforeach</div><small>Solo muestra las metas asociadas arriba. Marca una o varias; las no seleccionadas continúan en el total general del proyecto.</small></fieldset>
            <label class="flex items-center gap-3 text-sm font-semibold text-slate-700"><input type="checkbox" name="activo" value="1" @checked(old('activo', $project->exists ? $project->activo : true))> Proyecto activo</label>
        </div>
        @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="flex flex-wrap gap-3"><button class="btn-primary">Guardar proyecto</button><a class="btn-secondary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'metas-proyectos']) }}">Cancelar</a></div>
    </form>
</div>
<script>
    (() => {
        const tipo = document.getElementById('tipo-focalizacion');
        const unico = document.getElementById('municipio-unico-field');
        const varios = document.getElementById('municipios-regionalizacion-field');
        const regionalizacionModo = document.getElementById('regionalizacion-modo');
        const metasRegionalizacion = document.getElementById('metas-regionalizacion-field');
        const metasAsociadas = document.getElementById('metas-producto-asociadas');
        const metasRegionalizacionList = document.getElementById('metas-regionalizacion-list');
        const actualizar = () => {
            const municipal = tipo?.value === 'municipal';
            unico?.classList.toggle('hidden', !municipal);
            varios?.classList.toggle('hidden', municipal || !tipo?.value);
            metasRegionalizacion?.classList.toggle('hidden', regionalizacionModo?.value !== 'meta');
            const idsAsociadas = Array.from(metasAsociadas?.selectedOptions ?? []).map((option) => option.value);
            const seleccionadasAntes = new Set(Array.from(metasRegionalizacionList?.querySelectorAll('input:checked') ?? []).map((input) => input.value));
            if (metasRegionalizacionList && metasAsociadas) {
                metasRegionalizacionList.replaceChildren(...idsAsociadas.map((id) => {
                    const opcionAsociada = Array.from(metasAsociadas.options).find((option) => option.value === id);
                    const label = document.createElement('label');
                    label.className = 'flex items-start gap-2 rounded-lg border border-slate-200 bg-white p-2 text-sm';
                    const input = document.createElement('input');
                    input.className = '!mt-1 !w-auto shrink-0';
                    input.type = 'checkbox';
                    input.name = 'regionalizacion_meta_ids[]';
                    input.value = id;
                    input.checked = seleccionadasAntes.has(id);
                    label.append(input);
                    const text = document.createElement('span');
                    text.textContent = opcionAsociada?.textContent ?? id;
                    label.append(text);
                    return label;
                }));
            }
            if (metasRegionalizacion) metasRegionalizacion.disabled = regionalizacionModo?.value !== 'meta' || idsAsociadas.length === 0;
        };
        tipo?.addEventListener('change', actualizar);
        regionalizacionModo?.addEventListener('change', actualizar);
        metasAsociadas?.addEventListener('change', actualizar);
        actualizar();

        const lista = document.getElementById('municipios-regionalizacion');
        const seleccionar = (predicate) => Array.from(lista?.options ?? []).forEach((option) => { option.selected = predicate(option); });
        document.querySelector('[data-municipios-todos]')?.addEventListener('click', () => seleccionar(() => true));
        document.querySelector('[data-municipios-limpiar]')?.addEventListener('click', () => seleccionar(() => false));
        document.querySelector('[data-municipios-excepto-villavicencio]')?.addEventListener('click', () => seleccionar((option) => !option.textContent.toLowerCase().includes('villavicencio')));
    })();
</script>
@endsection
