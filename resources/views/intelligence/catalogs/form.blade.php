@extends('layouts.app', ['title' => ($record ? 'Editar' : 'Crear').' · '.$catalog->title().' · SIID 2.0'])

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <header>
        <p class="eyebrow">In-Orbit Intelligence</p>
        <h1 class="page-title">{{ $record ? 'Editar registro' : 'Nuevo registro' }}</h1>
        <p class="page-subtitle">{{ $catalog->title() }}</p>
    </header>

    <form method="post" action="{{ $record ? route($catalog->routeName().'.update', $record) : route($catalog->routeName().'.store') }}" class="panel space-y-4">
        @csrf
        @if($record)
            @method('PATCH')
        @endif

        @foreach($catalog->fields() as $field)
            @if($field->onForm)
                @if($field->input === 'boolean')
                    <input type="hidden" name="{{ $field->formKey() }}" value="0">
                    <label class="check">
                        <input type="checkbox" name="{{ $field->formKey() }}" value="1" @checked(filter_var(old($field->formKey(), $catalog->formValue($field, $record)), FILTER_VALIDATE_BOOLEAN))>
                        {{ $field->label }}
                    </label>
                @elseif($field->input === 'select')
                    <label class="field">
                        <span>{{ $field->label }}</span>
                        <select name="{{ $field->formKey() }}" @required($field->required)>
                            <option value="">Seleccione</option>
                            @foreach($field->options ?? [] as $value => $label)
                                <option value="{{ $value }}" @selected((string) old($field->formKey(), $catalog->formValue($field, $record)) === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @if($field->hint)<small>{{ $field->hint }}</small>@endif
                    </label>
                @elseif($field->input === 'dependencia')
                    <label class="field">
                        <span>{{ $field->label }}</span>
                        <select name="dependencia_id" @required($field->required)>
                            <option value="">Seleccione</option>
                            @foreach($dependencias as $dependencia)
                                <option value="{{ $dependencia->id }}" @selected((string) old('dependencia_id', $catalog->formValue($field, $record)) === (string) $dependencia->id)>
                                    {{ $dependencia->codigo }} — {{ $dependencia->nombre }}{{ $dependencia->activo ? '' : ' (inactiva)' }}
                                </option>
                            @endforeach
                        </select>
                        @if($field->hint)<small>{{ $field->hint }}</small>@endif
                    </label>
                @elseif($field->input === 'lookup')
                    <label class="field">
                        <span>{{ $field->label }}</span>
                        <select
                            name="{{ $field->formKey() }}"
                            @required($field->required)
                            @if($field->dependsOn) data-catalog-child data-depends-on="{{ $field->dependsOn }}" @endif
                        >
                            <option value="">Seleccione</option>
                            @foreach($lookups[$field->formKey()] ?? [] as $option)
                                <option
                                    value="{{ $option->getKey() }}"
                                    @if($field->parentAttribute) data-parent="{{ $option->{$field->parentAttribute} }}" @endif
                                    @selected((string) old($field->formKey(), $catalog->formValue($field, $record)) === (string) $option->getKey())
                                >{{ $catalog->lookupOptionLabel($field, $option) }}</option>
                            @endforeach
                        </select>
                        @if($field->hint)<small>{{ $field->hint }}</small>@endif
                    </label>
                @elseif($field->input === 'decimal')
                    <label class="field">
                        <span>{{ $field->label }}</span>
                        <input name="{{ $field->formKey() }}" inputmode="decimal" value="{{ old($field->formKey(), $catalog->formValue($field, $record)) }}" @required($field->required)>
                        @if($field->hint)<small>{{ $field->hint }}</small>@endif
                    </label>
                @elseif($field->input === 'textarea')
                    <label class="field">
                        <span>{{ $field->label }}</span>
                        <textarea name="{{ $field->formKey() }}" rows="4" @if($field->maxLength) maxlength="{{ $field->maxLength }}" @endif>{{ old($field->formKey(), $catalog->formValue($field, $record)) }}</textarea>
                        @if($field->hint)<small>{{ $field->hint }}</small>@endif
                    </label>
                @else
                    <label class="field">
                        <span>{{ $field->label }}</span>
                        <input
                            name="{{ $field->formKey() }}"
                            value="{{ old($field->formKey(), $catalog->formValue($field, $record)) }}"
                            @if($field->input === 'number') type="number" @else type="text" @endif
                            @if($field->maxLength && $field->input !== 'number') maxlength="{{ $field->maxLength }}" @endif
                            @if($field->minNumber !== null) min="{{ $field->minNumber }}" @endif
                            @if($field->maxNumber !== null) max="{{ $field->maxNumber }}" @endif
                            @required($field->required)
                        >
                        @if($field->hint)<small>{{ $field->hint }}</small>@endif
                    </label>
                @endif
            @endif
        @endforeach

        <div class="flex flex-wrap items-center gap-3">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route($catalog->routeName().'.index') }}">Volver al listado</a>
        </div>
    </form>

    <script>
        document.querySelectorAll('[data-catalog-child]').forEach((child) => {
            const parent = document.querySelector(`[name="${child.dataset.dependsOn}"]`);
            if (!parent) return;
            const options = [...child.options];
            const apply = () => {
                const selected = parent.value;
                options.forEach((option) => {
                    if (!option.value) return;
                    option.hidden = selected !== '' && option.dataset.parent !== selected;
                });
                const current = child.selectedOptions[0];
                if (current && current.hidden) child.value = '';
            };
            parent.addEventListener('change', apply);
            apply();
        });
    </script>

    @if($countsRecord ?? null)
        <section class="panel space-y-3">
            <div>
                <h2 class="text-lg font-semibold">Vínculos del registro</h2>
                <p class="mt-1 text-sm text-slate-500">Registros relacionados con este. Haga clic en un valor para ver cuáles son.</p>
            </div>
            <dl class="grid gap-3 sm:grid-cols-2">
                @foreach($catalog->counts() as $relation => $label)
                    @php($countValue = (int) $countsRecord->{\Illuminate\Support\Str::snake($relation).'_count'})
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 text-sm">@include('intelligence.catalogs.partials.count-value', ['record' => $countsRecord])</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        @if($catalog->countDetails() !== [])
            @include('intelligence.catalogs.partials.count-detail-dialog')
        @endif
    @endif

    @if($record)
        @can('delete', $record)
            <form method="post" action="{{ route($catalog->routeName().'.destroy', $record) }}" onsubmit="return confirm(@js($catalog->destroyConfirm()))">
                @csrf
                @method('DELETE')
                <button class="btn-danger">{{ $catalog->destroyLabel() }}</button>
            </form>
        @endcan
    @endif
</div>
@endsection
