@extends('layouts.app', ['title' => 'Editar seguimiento · '.$seguimiento->etiqueta().' · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Reporte mensual de metas</p>
            <h1 class="page-title">Editar seguimiento</h1>
            <p class="page-subtitle max-w-3xl">
                Corrija la vigencia, el mes de corte o la observación del seguimiento. Esta acción no recarga la pasiva ni recalcula datos; solo actualiza la identificación del corte.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}">Volver al seguimiento</a>
            <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.index') }}">Todos los seguimientos</a>
        </div>
    </header>

    <section class="panel max-w-4xl">
        <form method="post" action="{{ route('intelligence.reporte-mensual.update', $seguimiento) }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <div class="grid gap-4 md:grid-cols-2">
                <label class="field">
                    <span>Vigencia</span>
                    <input type="number" name="vigencia" value="{{ old('vigencia', $seguimiento->vigencia) }}" min="2024" max="2035" required>
                    @error('vigencia')<small class="text-red-600">{{ $message }}</small>@enderror
                </label>

                <label class="field">
                    <span>Mes de corte</span>
                    <select name="mes" required>
                        @foreach([1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $numero => $nombre)
                            <option value="{{ $numero }}" @selected((int) old('mes', $seguimiento->mes) === $numero)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('mes')<small class="text-red-600">{{ $message }}</small>@enderror
                </label>
            </div>

            <label class="field">
                <span>Observación</span>
                <textarea name="observacion" rows="4" maxlength="2000">{{ old('observacion', $seguimiento->observacion) }}</textarea>
                @error('observacion')<small class="text-red-600">{{ $message }}</small>@enderror
            </label>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">Importante</p>
                <p class="mt-1">Si cambia septiembre por agosto, la fecha de corte se actualizará automáticamente al último día de agosto. Los archivos cargados y los reportes ya asociados permanecen dentro de este mismo seguimiento.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button class="btn-primary">Guardar cambios</button>
                <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}">Cancelar</a>
            </div>
        </form>
    </section>
</div>
@endsection
