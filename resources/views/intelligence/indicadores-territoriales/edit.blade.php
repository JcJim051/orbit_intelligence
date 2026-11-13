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

        <form method="post" action="{{ route('intelligence.indicadores-territoriales.update', $indicador) }}" class="space-y-6 p-6">
            @csrf @method('PATCH')
            <div class="grid gap-4 md:grid-cols-2">
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Nombre *</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="nombre" required>{{ $value('nombre') }}</textarea></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Convención o sigla</span><input class="w-full rounded-xl border-slate-300" name="convencion" value="{{ $value('convencion') }}" placeholder="Abreviación con la que se identifica el indicador"></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Definición</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="definicion">{{ $value('definicion') }}</textarea></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Objetivo</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="objetivo">{{ $value('objetivo') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Área temática</span><input class="w-full rounded-xl border-slate-300" name="area_tematica" value="{{ $value('area_tematica') }}"></label>
                <label><span class="mb-1 block text-sm font-bold">Tipo de indicador</span><input class="w-full rounded-xl border-slate-300" name="tipo_indicador" value="{{ $value('tipo_indicador') }}"></label>
                <label><span class="mb-1 block text-sm font-bold">Unidad de medida</span><select class="w-full rounded-xl border-slate-300" name="unidad_medida"><option value="">Seleccione</option>@if($value('unidad_medida') && ! array_key_exists($value('unidad_medida'), $unidadesMedida))<option value="{{ $value('unidad_medida') }}" selected>{{ $value('unidad_medida') }} (valor importado)</option>@endif @foreach($unidadesMedida as $key => $label)<option value="{{ $key }}" @selected($value('unidad_medida') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">Periodicidad normalizada</span><select class="w-full rounded-xl border-slate-300" name="periodicidad"><option value="">Seleccione</option>@foreach($periodicidades as $key => $label)<option value="{{ $key }}" @selected($value('periodicidad') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Periodicidad informada originalmente</span><input class="w-full rounded-xl border-slate-300" name="periodicidad_original" value="{{ $value('periodicidad_original') }}"></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Fórmula o regla de cálculo</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="formula_calculo">{{ $value('formula_calculo') }}</textarea><small class="text-slate-500">Referencia metodológica. La plataforma almacena el valor oficial reportado y no lo recalcula.</small></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Variables del cálculo</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="variables_calculo">{{ $value('variables_calculo') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Fuente de información</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="fuente_informacion">{{ $value('fuente_informacion') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Responsable de la información</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="responsable_informacion">{{ $value('responsable_informacion') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Desagregaciones</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="desagregaciones">{{ $value('desagregaciones') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Cobertura geográfica</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="cobertura_geografica">{{ $value('cobertura_geografica') }}</textarea></label>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><span class="block text-sm font-bold">Valor de línea base</span><strong class="mt-1 block">{{ $indicador->linea_base_valor ?? 'Pendiente de histórico' }}</strong><small class="text-slate-500">Se calcula con el dato aprobado más antiguo.</small></div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><span class="block text-sm font-bold">Periodo de línea base</span><strong class="mt-1 block">{{ $indicador->linea_base_periodo ?? 'Pendiente de histórico' }}</strong><small class="text-slate-500">No se modifica manualmente.</small></div>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Observaciones técnicas internas</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="observaciones_tecnicas">{{ $value('observaciones_tecnicas') }}</textarea></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Limitaciones del indicador</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="limitaciones">{{ $value('limitaciones') }}</textarea></label>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5">
                <a class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700" href="{{ route('intelligence.indicadores-territoriales.show', $indicador) }}">Cancelar</a>
                <button class="rounded-xl border border-emerald-300 bg-white px-5 py-2.5 text-sm font-bold text-emerald-700">Guardar ficha</button>
                @if($canReview)<button formaction="{{ route('intelligence.indicadores-territoriales.approve', $indicador) }}" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">Aprobar ficha técnica</button>@endif
            </div>
        </form>
    </section>
</div>
