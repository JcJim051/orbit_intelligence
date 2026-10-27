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

        <form method="post" action="{{ route('intelligence.indicadores-territoriales.store') }}" class="space-y-6 p-6">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="mb-1 block text-sm font-bold">Código *</span><input class="w-full rounded-xl border-slate-300" name="codigo" value="{{ old('codigo') }}" maxlength="80" required placeholder="Ej. IT-0154"></label>
                <label><span class="mb-1 block text-sm font-bold">Dependencia responsable</span><select class="w-full rounded-xl border-slate-300" name="dependencia_id"><option value="">Sin relacionar</option>@foreach($dependencias as $dependencia)<option value="{{ $dependencia->id }}" @selected((string) old('dependencia_id') === (string) $dependencia->id)>{{ $dependencia->codigo ? $dependencia->codigo.' — ' : '' }}{{ $dependencia->nombre }}</option>@endforeach</select></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Nombre del indicador *</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="nombre" required>{{ old('nombre') }}</textarea></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Convención o sigla</span><input class="w-full rounded-xl border-slate-300" name="convencion" value="{{ old('convencion') }}" placeholder="Abreviación con la que se identifica el indicador"></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Definición</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="definicion">{{ old('definicion') }}</textarea></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Objetivo</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="objetivo">{{ old('objetivo') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Área temática</span><input class="w-full rounded-xl border-slate-300" name="area_tematica" value="{{ old('area_tematica') }}"></label>
                <label><span class="mb-1 block text-sm font-bold">Tipo de indicador</span><input class="w-full rounded-xl border-slate-300" name="tipo_indicador" value="{{ old('tipo_indicador') }}"></label>
                <label><span class="mb-1 block text-sm font-bold">Unidad de medida</span><select class="w-full rounded-xl border-slate-300" name="unidad_medida"><option value="">Por definir</option>@foreach($unidadesMedida as $key => $label)<option value="{{ $key }}" @selected(old('unidad_medida') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label><span class="mb-1 block text-sm font-bold">Periodicidad</span><select class="w-full rounded-xl border-slate-300" name="periodicidad"><option value="">Por definir</option>@foreach($periodicidades as $key => $label)<option value="{{ $key }}" @selected(old('periodicidad') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Periodicidad informada originalmente</span><input class="w-full rounded-xl border-slate-300" name="periodicidad_original" value="{{ old('periodicidad_original') }}"></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Fórmula o regla de cálculo</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="formula_calculo">{{ old('formula_calculo') }}</textarea><small class="text-slate-500">Se conserva como referencia metodológica; la plataforma no recalcula el valor.</small></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Variables del cálculo</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="variables_calculo">{{ old('variables_calculo') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Fuente de información</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="fuente_informacion">{{ old('fuente_informacion') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Responsable de la información</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="responsable_informacion">{{ old('responsable_informacion') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Desagregaciones</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="desagregaciones">{{ old('desagregaciones') }}</textarea></label>
                <label><span class="mb-1 block text-sm font-bold">Cobertura geográfica</span><textarea class="w-full rounded-xl border-slate-300" rows="2" name="cobertura_geografica">{{ old('cobertura_geografica') }}</textarea></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Enlace de información asignado</span><select class="w-full rounded-xl border-slate-300" name="asignado_a"><option value="">Asignar después</option>@foreach($revisores as $revisor)<option value="{{ $revisor->id }}" @selected((string) old('asignado_a') === (string) $revisor->id)>{{ $revisor->name }} · {{ $revisor->email }}</option>@endforeach</select></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Observaciones técnicas internas</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="observaciones_tecnicas">{{ old('observaciones_tecnicas') }}</textarea></label>
                <label class="md:col-span-2"><span class="mb-1 block text-sm font-bold">Limitaciones del indicador</span><textarea class="w-full rounded-xl border-slate-300" rows="3" name="limitaciones">{{ old('limitaciones') }}</textarea></label>
            </div>
            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5"><a class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700" href="{{ route('intelligence.indicadores-territoriales.index') }}">Cancelar</a><button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white">Crear indicador</button></div>
        </form>
    </section>
</div>
