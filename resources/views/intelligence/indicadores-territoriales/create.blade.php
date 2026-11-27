<div class="space-y-5">
    <div class="flex items-center justify-between gap-3">
        <a class="text-sm font-bold text-emerald-700 hover:underline" href="{{ route('intelligence.indicadores-territoriales.index') }}">← Volver a indicadores</a>
        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">Creación manual</span>
    </div>

    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><strong>Revise la información del formulario.</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-950 to-emerald-800 p-6 text-white">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-200">Plan Estadístico Territorial</p>
            <h1 class="mt-2 text-2xl font-black">Nuevo indicador territorial</h1>
            <p class="mt-2 max-w-3xl text-sm text-emerald-100">Cree el registro base y, si lo desea, asígnelo inmediatamente al enlace que verificará la ficha técnica y su serie histórica.</p>
        </div>

        <form method="post" action="{{ route('intelligence.indicadores-territoriales.store') }}" enctype="multipart/form-data" class="space-y-6 p-6">
            @csrf
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-3 rounded-xl bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">1. Identificación</div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-950"><strong>Código automático</strong><span class="mt-1 block text-emerald-800">Se asignará el siguiente IT-#### disponible.</span></div>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Dependencia responsable *</span><select required class="w-full rounded-xl border-slate-300" name="dependencia_id"><option value="">Seleccione</option>@foreach($dependencias as $dependencia)<option value="{{ $dependencia->id }}" @selected((string) old('dependencia_id') === (string) $dependencia->id)>{{ $dependencia->codigo ? $dependencia->codigo.' — ' : '' }}{{ $dependencia->nombre }}</option>@endforeach</select></label>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Nombre del indicador *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="nombre">{{ old('nombre') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Convención o sigla</span><input class="w-full rounded-xl border-slate-300" name="convencion" value="{{ old('convencion') }}"></label>
                <label><span class="mb-1 block text-sm font-bold">Área temática *</span><input required list="areas-tematicas" class="w-full rounded-xl border-slate-300" name="area_tematica" value="{{ old('area_tematica') }}" placeholder="Seleccione o escriba"></label>
                <label><span class="mb-1 block text-sm font-bold">Tipo de indicador *</span><input required list="tipos-indicador" class="w-full rounded-xl border-slate-300" name="tipo_indicador" value="{{ old('tipo_indicador') }}" placeholder="Seleccione o escriba"></label>
                <label><span class="mb-1 block text-sm font-bold">Unidad de medida *</span><select required class="w-full rounded-xl border-slate-300" name="unidad_medida"><option value="">Seleccione</option>@foreach($unidadesMedida as $key => $label)<option value="{{ $key }}" @selected(old('unidad_medida') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">Periodicidad *</span><select required class="w-full rounded-xl border-slate-300" name="periodicidad"><option value="">Seleccione</option>@foreach($periodicidades as $key => $label)<option value="{{ $key }}" @selected(old('periodicidad') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">Sentido deseado *</span><select required class="w-full rounded-xl border-slate-300" name="sentido_deseado"><option value="">Seleccione</option>@foreach(['aumentar' => 'Aumentar', 'disminuir' => 'Disminuir', 'mantener' => 'Mantener'] as $key => $label)<option value="{{ $key }}" @selected(old('sentido_deseado') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">Periodicidad original</span><input class="w-full rounded-xl border-slate-300" name="periodicidad_original" value="{{ old('periodicidad_original') }}"></label>

                <div class="lg:col-span-3 mt-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">2. Metodología</div>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Definición *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="definicion">{{ old('definicion') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Objetivo *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="objetivo">{{ old('objetivo') }}</textarea></label>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Fórmula o regla de cálculo *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="formula_calculo">{{ old('formula_calculo') }}</textarea><small class="text-slate-500">La plataforma conserva la fórmula, pero no recalcula el dato oficial.</small></label>
                <label><span class="mb-1 block text-sm font-bold">Variables del cálculo</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="variables_calculo">{{ old('variables_calculo') }}</textarea></label>

                <div class="lg:col-span-3 mt-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">3. Fuente y alcance</div>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Fuente de información *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="fuente_informacion">{{ old('fuente_informacion') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Responsable de la información *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="responsable_informacion">{{ old('responsable_informacion') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Cobertura geográfica *</span><input required list="coberturas-geograficas" class="w-full rounded-xl border-slate-300" name="cobertura_geografica" value="{{ old('cobertura_geografica') }}" placeholder="Seleccione o escriba"></label>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Desagregaciones</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="desagregaciones">{{ old('desagregaciones') }}</textarea></label>

                <div class="lg:col-span-3 mt-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">4. Relaciones y publicación</div>
                <label><span class="mb-1 block text-sm font-bold">Meta producto relacionada</span><select class="w-full rounded-xl border-slate-300" name="meta_producto_id"><option value="">No aplica</option>@foreach($metasProducto as $meta)<option value="{{ $meta->id }}" @selected((string) old('meta_producto_id') === (string) $meta->id)>{{ $meta->codigo }} — {{ $meta->nombre }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">Indicador de resultado relacionado</span><select class="w-full rounded-xl border-slate-300" name="indicador_resultado_id"><option value="">No aplica</option>@foreach($indicadoresResultado as $resultado)<option value="{{ $resultado->id }}" @selected((string) old('indicador_resultado_id') === (string) $resultado->id)>{{ $resultado->codigo }} — {{ $resultado->nombre }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">URL de ficha pública</span><input class="w-full rounded-xl border-slate-300" type="url" name="url_ficha" value="{{ old('url_ficha') }}"></label>
                <label><span class="mb-1 block text-sm font-bold">Tipo de tablero</span><select class="w-full rounded-xl border-slate-300" name="tablero_tipo"><option value="">Sin tablero</option><option value="looker" @selected(old('tablero_tipo') === 'looker')>Looker Studio</option><option value="superset" @selected(old('tablero_tipo') === 'superset')>Apache Superset</option></select></label>
                <label><span class="mb-1 block text-sm font-bold">URL embed o UUID</span><input class="w-full rounded-xl border-slate-300" name="tablero_referencia" value="{{ old('tablero_referencia') }}"></label>
                <label><span class="mb-1 block text-sm font-bold">Ficha técnica en PDF</span><input class="w-full rounded-xl border border-slate-300 bg-white p-2" type="file" name="ficha_tecnica_pdf" accept="application/pdf,.pdf"></label>

                <div class="lg:col-span-3 mt-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">5. Asignación y control interno</div>
                @if($selfAssigned)
                    <div class="lg:col-span-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900"><strong>Responsable interno:</strong> quedará asignado automáticamente a {{ auth()->user()->name }}.</div>
                @else
                    <label class="lg:col-span-3"><span class="mb-1 block text-sm font-bold">Enlace de información asignado</span><select class="w-full rounded-xl border-slate-300" name="asignado_a"><option value="">Asignar después</option>@foreach($revisores as $revisor)<option value="{{ $revisor->id }}" @selected((string) old('asignado_a') === (string) $revisor->id)>{{ $revisor->name }} · {{ $revisor->email }}</option>@endforeach</select></label>
                @endif
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Observaciones técnicas internas</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="observaciones_tecnicas">{{ old('observaciones_tecnicas') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Limitaciones del indicador</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="limitaciones">{{ old('limitaciones') }}</textarea></label>

                <datalist id="areas-tematicas">@foreach($areasTematicas as $opcion)<option value="{{ $opcion }}">@endforeach</datalist>
                <datalist id="tipos-indicador">@foreach($tiposIndicador as $opcion)<option value="{{ $opcion }}">@endforeach</datalist>
                <datalist id="coberturas-geograficas">@foreach($coberturasGeograficas as $opcion)<option value="{{ $opcion }}">@endforeach</datalist>
            </div>
            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5"><a class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700" href="{{ route('intelligence.indicadores-territoriales.index') }}">Cancelar</a><button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">Crear indicador</button></div>
        </form>
    </section>
</div>
