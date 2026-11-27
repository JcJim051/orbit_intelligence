@php($value = fn (string $field) => old($field, $indicador->{$field}))

<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a class="text-sm font-bold text-emerald-700 hover:underline" href="{{ route('intelligence.indicadores-territoriales.show', $indicador) }}">← Volver a la vista del indicador</a>
        <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">{{ $estados[$indicador->estado] ?? $indicador->estado }}</span>
    </div>

    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><strong>Revise la información de la ficha.</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-950 to-emerald-800 p-6 text-white">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-200">{{ $indicador->codigo }} · Edición</p>
            <h1 class="mt-2 text-2xl font-black">Ficha técnica del indicador</h1>
            <p class="mt-2 max-w-4xl text-sm text-emerald-100">{{ $indicador->nombre }}</p>
        </div>

        <form method="post" action="{{ route('intelligence.indicadores-territoriales.update', $indicador) }}" enctype="multipart/form-data" class="space-y-6 p-6">
            @csrf @method('PATCH')
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="lg:col-span-3 rounded-xl bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">1. Identificación</div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-950"><strong>Código territorial</strong><span class="mt-1 block text-emerald-800">{{ $indicador->codigo }}</span></div>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Dependencia responsable *</span><select required class="w-full rounded-xl border-slate-300" name="dependencia_id"><option value="">Seleccione</option>@foreach($dependencias as $dependencia)<option value="{{ $dependencia->id }}" @selected((string) $value('dependencia_id') === (string) $dependencia->id)>{{ $dependencia->codigo ? $dependencia->codigo.' — ' : '' }}{{ $dependencia->nombre }}</option>@endforeach</select></label>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Nombre *</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="nombre" required>{{ $value('nombre') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Convención o sigla</span><input class="w-full rounded-xl border-slate-300" name="convencion" value="{{ $value('convencion') }}"></label>
                <label><span class="mb-1 block text-sm font-bold">Área temática *</span><input required list="areas-tematicas" class="w-full rounded-xl border-slate-300" name="area_tematica" value="{{ $value('area_tematica') }}" placeholder="Seleccione o escriba"></label>
                <label><span class="mb-1 block text-sm font-bold">Tipo de indicador *</span><input required list="tipos-indicador" class="w-full rounded-xl border-slate-300" name="tipo_indicador" value="{{ $value('tipo_indicador') }}" placeholder="Seleccione o escriba"></label>
                <label><span class="mb-1 block text-sm font-bold">Unidad de medida *</span><select required class="w-full rounded-xl border-slate-300" name="unidad_medida"><option value="">Seleccione</option>@if($value('unidad_medida') && ! array_key_exists($value('unidad_medida'), $unidadesMedida))<option value="{{ $value('unidad_medida') }}" selected>{{ $value('unidad_medida') }} (valor importado)</option>@endif @foreach($unidadesMedida as $key => $label)<option value="{{ $key }}" @selected($value('unidad_medida') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">Periodicidad *</span><select required class="w-full rounded-xl border-slate-300" name="periodicidad"><option value="">Seleccione</option>@foreach($periodicidades as $key => $label)<option value="{{ $key }}" @selected($value('periodicidad') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">Sentido deseado *</span><select required class="w-full rounded-xl border-slate-300" name="sentido_deseado"><option value="">Seleccione</option>@foreach(['aumentar' => 'Aumentar', 'disminuir' => 'Disminuir', 'mantener' => 'Mantener'] as $key => $label)<option value="{{ $key }}" @selected($value('sentido_deseado') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">Periodicidad original</span><input class="w-full rounded-xl border-slate-300" name="periodicidad_original" value="{{ $value('periodicidad_original') }}"></label>

                <div class="lg:col-span-3 mt-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">2. Metodología</div>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Definición *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="definicion">{{ $value('definicion') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Objetivo *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="objetivo">{{ $value('objetivo') }}</textarea></label>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Fórmula o regla de cálculo *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="formula_calculo">{{ $value('formula_calculo') }}</textarea><small class="text-slate-500">La plataforma conserva la fórmula, pero no recalcula el dato oficial.</small></label>
                <label><span class="mb-1 block text-sm font-bold">Variables del cálculo</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="variables_calculo">{{ $value('variables_calculo') }}</textarea></label>

                <div class="lg:col-span-3 mt-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">3. Fuente y alcance</div>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Fuente de información *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="fuente_informacion">{{ $value('fuente_informacion') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Responsable de la información *</span><textarea required class="w-full rounded-xl border-slate-300" rows="2" name="responsable_informacion">{{ $value('responsable_informacion') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Cobertura geográfica *</span><input required list="coberturas-geograficas" class="w-full rounded-xl border-slate-300" name="cobertura_geografica" value="{{ $value('cobertura_geografica') }}" placeholder="Seleccione o escriba"></label>
                <label class="lg:col-span-2"><span class="mb-1 block text-sm font-bold">Desagregaciones</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="desagregaciones">{{ $value('desagregaciones') }}</textarea></label>

                <div class="lg:col-span-3 mt-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">4. Relaciones y publicación</div>
                <label><span class="mb-1 block text-sm font-bold">Meta producto relacionada</span><select class="w-full rounded-xl border-slate-300" name="meta_producto_id"><option value="">No aplica</option>@foreach($metasProducto as $meta)<option value="{{ $meta->id }}" @selected((string) $value('meta_producto_id') === (string) $meta->id)>{{ $meta->codigo }} — {{ $meta->nombre }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">Indicador de resultado relacionado</span><select class="w-full rounded-xl border-slate-300" name="indicador_resultado_id"><option value="">No aplica</option>@foreach($indicadoresResultado as $resultado)<option value="{{ $resultado->id }}" @selected((string) $value('indicador_resultado_id') === (string) $resultado->id)>{{ $resultado->codigo }} — {{ $resultado->nombre }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">URL de ficha pública</span><input class="w-full rounded-xl border-slate-300" type="url" name="url_ficha" value="{{ $value('url_ficha') }}"></label>
                <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <label><span class="mb-1 block text-sm font-bold">Ficha técnica en PDF</span><input class="w-full rounded-xl border border-slate-300 bg-white p-2" type="file" name="ficha_tecnica_pdf" accept="application/pdf,.pdf"><small class="mt-1 block text-slate-500">PDF de máximo 10 MB. Un archivo nuevo reemplaza al actual.</small></label>
                    @if($indicador->ficha_tecnica_pdf_path)
                        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm"><a class="font-bold text-emerald-700 hover:underline" href="{{ $indicador->fichaTecnicaPdfUrl() }}" target="_blank" rel="noopener">Ver PDF cargado ↗</a><label class="inline-flex items-center gap-2 text-rose-700"><input type="checkbox" name="eliminar_ficha_tecnica_pdf" value="1"> Eliminar archivo actual</label></div>
                    @endif
                </div>
                <label><span class="mb-1 block text-sm font-bold">Tipo de tablero</span><select class="w-full rounded-xl border-slate-300" name="tablero_tipo"><option value="">Sin tablero</option><option value="looker" @selected($value('tablero_tipo') === 'looker')>Looker Studio</option><option value="superset" @selected($value('tablero_tipo') === 'superset')>Apache Superset</option></select></label>
                <label><span class="mb-1 block text-sm font-bold">URL embed o UUID</span><input class="w-full rounded-xl border-slate-300" name="tablero_referencia" value="{{ $value('tablero_referencia') }}"></label>

                <div class="lg:col-span-3 mt-2 rounded-xl bg-slate-100 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-slate-600">5. Control interno</div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><span class="block text-sm font-bold">Valor de línea base</span><strong class="mt-1 block">{{ $indicador->linea_base_valor ?? 'Pendiente de histórico' }}</strong><small class="text-slate-500">Se calcula con el dato aprobado más antiguo.</small></div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><span class="block text-sm font-bold">Periodo de línea base</span><strong class="mt-1 block">{{ $indicador->linea_base_periodo ?? 'Pendiente de histórico' }}</strong><small class="text-slate-500">No se modifica manualmente.</small></div>
                <label><span class="mb-1 block text-sm font-bold">Limitaciones del indicador</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="limitaciones">{{ $value('limitaciones') }}</textarea></label>
                <label class="lg:col-span-3"><span class="mb-1 block text-sm font-bold">Observaciones técnicas internas</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="observaciones_tecnicas">{{ $value('observaciones_tecnicas') }}</textarea></label>

                <datalist id="areas-tematicas">@foreach($areasTematicas as $opcion)<option value="{{ $opcion }}">@endforeach</datalist>
                <datalist id="tipos-indicador">@foreach($tiposIndicador as $opcion)<option value="{{ $opcion }}">@endforeach</datalist>
                <datalist id="coberturas-geograficas">@foreach($coberturasGeograficas as $opcion)<option value="{{ $opcion }}">@endforeach</datalist>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
                <a class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700" href="{{ route('intelligence.indicadores-territoriales.show', $indicador) }}">Cancelar</a>
                <button class="rounded-xl border border-emerald-300 bg-white px-5 py-2.5 text-sm font-bold text-emerald-700">Guardar ficha</button>
                @if($canReview)<button formaction="{{ route('intelligence.indicadores-territoriales.approve', $indicador) }}" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">Aprobar ficha técnica</button>@endif
            </div>
        </form>
    </section>
</div>
