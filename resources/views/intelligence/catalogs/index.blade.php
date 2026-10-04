@extends('layouts.app', ['title' => $catalog->title().' · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">In-Orbit Intelligence</p>
            <h1 class="page-title">{{ $catalog->title() }}</h1>
            <p class="page-subtitle max-w-3xl">{{ $catalog->summary() }}</p>
        </div>
        @can('create', $catalog->modelClass())
            <a class="btn-primary" href="{{ route($catalog->routeName().'.create') }}">Nuevo registro</a>
        @endcan
    </header>

    @can('export', $catalog->modelClass())
        <section class="panel">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">Excel</h2>
                    <p class="mt-1 text-sm text-slate-500">Estas acciones están reservadas al administrador técnico.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <a class="btn-secondary" href="{{ route($catalog->routeName().'.export') }}">Descargar todo</a>
                    <a class="btn-secondary" href="{{ route($catalog->routeName().'.template') }}">Descargar plantilla</a>
                    @can('import', $catalog->modelClass())
                        <form method="post" action="{{ route($catalog->routeName().'.import') }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            @csrf
                            <label class="field sm:min-w-64">
                                <span class="sr-only">Archivo de Excel</span>
                                <input type="file" name="archivo" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                            </label>
                            <button class="btn-secondary">Importar desde Excel</button>
                        </form>
                    @endcan
                </div>
            </div>
        </section>
    @endcan

    @if($catalog->routeName() === 'intelligence.metas-producto')
        @can('import', $catalog->modelClass())
            <section class="panel border-emerald-100 bg-emerald-50/40">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Relación masiva Meta producto ↔ BPIN</h2>
                        <p class="mt-1 max-w-3xl text-sm text-slate-600">
                            Descargue la plantilla precargada con las metas producto de SIID, diligencie el BPIN y vuelva a cargarla.
                            Esta carga vincula proyectos sin borrar relaciones existentes.
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <a class="btn-secondary" href="{{ route('intelligence.metas-producto.projects.template') }}">Descargar plantilla precargada</a>
                        <form method="post" action="{{ route('intelligence.metas-producto.projects.import') }}" enctype="multipart/form-data" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            @csrf
                            <label class="field sm:min-w-64">
                                <span class="sr-only">Plantilla de relaciones</span>
                                <input type="file" name="archivo_relaciones" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                            </label>
                            <button class="btn-primary">Cargar vínculos BPIN</button>
                        </form>
                    </div>
                </div>
            </section>
        @endcan
    @endif

    <section class="panel">
        <form method="get" action="{{ route($catalog->routeName().'.index') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="field xl:col-span-2">
                <span>Buscar</span>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Código, nombre u otro texto del catálogo">
            </label>
            @if(in_array('activo', $catalog->filters(), true))
                <label class="field">
                    <span>Estado</span>
                    <select name="activo">
                        <option value="" @selected($filters['activo'] === '')>Todos</option>
                        <option value="1" @selected($filters['activo'] === '1')>Activos</option>
                        <option value="0" @selected($filters['activo'] === '0')>Inactivos</option>
                    </select>
                </label>
            @endif
            @foreach($catalog->filters() as $filter)
                @continue($filter === 'activo')
                @php($field = $catalog->fieldByFormKey($filter) ?? $catalog->fieldByColumn($filter))
                @if($field && $field->input === 'select')
                    <label class="field">
                        <span>{{ $field->label }}</span>
                        <select name="{{ $filter }}">
                            <option value="">Todos</option>
                            @foreach($field->options ?? [] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters[$filter] ?? '') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @elseif($field && in_array($field->input, ['lookup', 'dependencia'], true))
                    <label class="field">
                        <span>{{ $field->label }}</span>
                        <select name="{{ $filter }}">
                            <option value="">Todos</option>
                            @foreach($lookups[$field->formKey()] ?? [] as $option)
                                <option value="{{ $option->getKey() }}" @selected(($filters[$filter] ?? '') === (string) $option->getKey())>{{ $catalog->lookupOptionLabel($field, $option) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            @endforeach
            <div class="flex items-end gap-2">
                <button class="btn-primary">Filtrar</button>
                <a class="btn-secondary" href="{{ route($catalog->routeName().'.index') }}">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold">Registros</h2>
            <p class="text-sm text-slate-500">{{ $records->count() }} en total</p>
        </div>
        <div class="overflow-x-auto">
            <table data-siid-datatable data-search-placeholder="Buscar en todos los campos visibles…" class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        @foreach($catalog->fields() as $field)
                            @if($field->listed)
                                <th class="px-4 py-3">{{ $field->label }}</th>
                            @endif
                        @endforeach
                        @foreach($catalog->counts() as $label)
                            <th class="px-4 py-3" title="Número de {{ mb_strtolower($label) }} de cada registro. Haga clic en el número para ver el detalle.">{{ $label }}</th>
                        @endforeach
                        @can('create', $catalog->modelClass())
                            <th class="px-4 py-3">Acciones</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($records as $record)
                        <tr>
                            @foreach($catalog->fields() as $field)
                                @if($field->listed)
                                    <td class="max-w-xs px-4 py-3 align-top">
                                        @if($field->input === 'boolean')
                                            <span class="status {{ $record->{$field->column} ? 'status-approved' : '' }}">{{ $catalog->display($field, $record) }}</span>
                                        @else
                                            <span class="line-clamp-3">{{ $catalog->display($field, $record) }}</span>
                                        @endif
                                    </td>
                                @endif
                            @endforeach
                            @foreach($catalog->counts() as $relation => $label)
                                @php($countValue = (int) $record->{\Illuminate\Support\Str::snake($relation).'_count'})
                                <td class="px-4 py-3 align-top">
                                    @include('intelligence.catalogs.partials.count-value')
                                </td>
                            @endforeach
                            @can('update', $record)
                                <td class="px-4 py-3 align-top">
                                    <a class="font-semibold text-indigo-700" href="{{ route($catalog->routeName().'.edit', $record) }}">Editar</a>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-8 text-center text-slate-500" colspan="{{ collect($catalog->fields())->where('listed', true)->count() + count($catalog->counts()) + 1 }}">No hay registros con esos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($catalog->countDetails() !== [])
        @include('intelligence.catalogs.partials.count-detail-dialog')
    @endif
</div>
@endsection
