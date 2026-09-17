@extends('layouts.app', ['title' => 'Importaciones SIG · SIID 2.0'])

@section('content')
<div class="space-y-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Ingreso controlado</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Importaciones desde QGIS</h1>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">Cada autorización crea una zona de carga aislada. Puede renovar el acceso del mismo usuario desde aquí; Gerencia sigue aprobando qué datos se publican.</p>
            <p class="mt-2 max-w-3xl text-sm text-slate-600">En QGIS use «Importar capa vectorial». Si la capa es EPSG:9377, conserve ese SRC; no hace falta convertirla a 4326.</p>
        </div>
        <div class="flex gap-2"><a class="btn-secondary" href="{{ route('admin.spatial-datasets.index') }}">Catálogo de datos</a><a class="btn-secondary" href="{{ route('admin.postgis.index') }}">Infraestructura SIG</a></div>
    </header>

    @if($credentials)
        <section class="panel border-emerald-300 bg-emerald-50/60">
            <div class="panel-head"><div><h2>Credencial nueva para QGIS</h2><span>Se muestra en esta respuesta. Copie los datos antes de salir de la página.</span></div></div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach(['host' => 'Servidor', 'port' => 'Puerto', 'database' => 'Base de datos', 'sslmode' => 'SSL', 'username' => 'Usuario', 'password' => 'Contraseña', 'schema' => 'Esquema', 'expires_at' => 'Vence'] as $key => $label)
                    <div class="rounded-xl border border-emerald-200 bg-white p-3"><p class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</p><p class="mt-1 break-all font-mono text-sm" data-copy-value="{{ $credentials[$key] }}">{{ $credentials[$key] }}</p></div>
                @endforeach
            </div>
            <p class="mt-4 text-sm text-emerald-900"><strong>QGIS:</strong> al importar, elija exactamente el esquema <code>{{ $credentials['schema'] }}</code> y conserve el SRC real de la capa.</p>
        </section>
    @endif

    <section class="panel" id="nueva-importacion">
        <div class="panel-head"><div><h2>Autorizar una carga</h2><span>Requiere que PostGIS esté configurado. Puede renovar el acceso cuando lo necesite, hasta por 7 días cada vez.</span></div></div>
        @if(!$postgis['configured'])
            <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Primero configure PostgreSQL en <a class="font-semibold underline" href="{{ route('admin.postgis.index') }}">Infraestructura SIG</a>.</p>
        @else
            <form method="post" action="{{ route('admin.spatial-imports.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <label class="field lg:col-span-2"><span>Nombre de la importación</span><input name="name" value="{{ old('name') }}" maxlength="160" required placeholder="Límites departamentales 2026"></label>
                <label class="field"><span>Sector responsable</span><input name="sector" value="{{ old('sector') }}" maxlength="120" required placeholder="Planeación"></label>
                <label class="field"><span>Vigencia de acceso</span><select name="valid_for_hours"><option value="24">24 horas</option><option value="72" selected>3 días</option><option value="168">7 días</option></select></label>
                <label class="field sm:col-span-2 lg:col-span-4"><span>Propósito y procedencia</span><textarea name="purpose" rows="2" maxlength="2000" placeholder="Origen, fecha de corte y uso esperado de la capa.">{{ old('purpose') }}</textarea></label>
                <button class="btn-primary sm:col-span-2 lg:col-span-4">Crear zona temporal y credencial</button>
            </form>
        @endif
    </section>

    <section class="space-y-4">
        <div><h2 class="text-xl font-semibold">Bandeja de importaciones</h2><p class="mt-1 text-sm text-slate-500">{{ $imports->count() }} procesos registrados.</p></div>
        @forelse($imports as $import)
            @php
                $importStatusUrl = route('admin.spatial-imports.index').'#import-'.$import->id;
            @endphp
            <details class="panel" id="import-{{ $import->id }}">
                <summary class="cursor-pointer list-none">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase text-indigo-600">{{ $import->sector }}</p><h3 class="mt-1 text-lg font-semibold">{{ $import->name }}</h3><p class="mt-1 text-sm text-slate-500"><code>{{ $import->staging_schema }}</code> · {{ $import->expires_at->isPast() ? 'acceso vencido' : 'acceso vigente hasta' }} {{ $import->expires_at->copy()->timezone('America/Bogota')->format('d/m/Y H:i') }} (Colombia) · {{ $import->contracts->count() }} capa(s) incorporada(s)</p></div><a class="status status-action {{ in_array($import->status, [\App\Enums\SpatialImportStatus::Profiled, \App\Enums\SpatialImportStatus::ContractDraft], true) ? 'status-approved' : '' }}" href="{{ $importStatusUrl }}" data-status-link title="Ver las capas y su estado">{{ $import->status === \App\Enums\SpatialImportStatus::Approved ? 'Zona QGIS con capas aprobadas' : $import->status->label() }}</a></div>
                </summary>
                <div class="mt-5 space-y-5">
                    @if($import->purpose)<p class="text-sm text-slate-700">{{ $import->purpose }}</p>@endif
                    @if($import->failure_message)<p class="rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $import->failure_message }}</p>@endif
                    @if(in_array($import->status, [\App\Enums\SpatialImportStatus::StagingReady, \App\Enums\SpatialImportStatus::Profiled, \App\Enums\SpatialImportStatus::ContractDraft, \App\Enums\SpatialImportStatus::Approved], true))
                        <form method="post" action="{{ route('admin.spatial-imports.access.store', $import) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4">
                            @csrf
                            <label class="field min-w-40"><span>Vigencia desde hoy</span><select name="valid_for_hours"><option value="24">24 horas</option><option value="72" selected>3 días</option><option value="168">7 días</option></select></label>
                            <button class="btn-secondary">{{ $import->expires_at->isPast() ? 'Reactivar acceso QGIS' : 'Restablecer o ampliar acceso QGIS' }}</button>
                            <p class="w-full text-xs text-slate-600">Conserva el mismo usuario, contraseña y esquema. También devuelve el control de las capas aún no incorporadas que hayan quedado bloqueadas por contratos anteriores. Las capas contratadas mantienen su estructura protegida y nada nuevo se publica automáticamente.</p>
                        </form>
                    @elseif($import->status === \App\Enums\SpatialImportStatus::Closed)
                        <p class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">Esta importación está cerrada y no admite renovación.</p>
                    @endif

                    @if(in_array($import->status, [\App\Enums\SpatialImportStatus::StagingReady, \App\Enums\SpatialImportStatus::Profiled, \App\Enums\SpatialImportStatus::ContractDraft, \App\Enums\SpatialImportStatus::Approved], true))
                        <form method="post" action="{{ route('admin.spatial-imports.profile.store', $import) }}" class="flex flex-wrap items-center gap-3">
                            @csrf<input type="hidden" name="confirm" value="1"><button class="btn-secondary">Actualizar capas cargadas</button><span class="text-xs text-slate-500">Úselo después de terminar la exportación en QGIS. No cambia lo que ya está publicado.</span>
                        </form>
                    @endif

                    @if(data_get($import->profile, 'tables'))
                        <div class="space-y-4">
                            @foreach(data_get($import->profile, 'tables', []) as $table)
                                @php
                                    $contract = $import->contracts->firstWhere('source_table', $table['name']);
                                    $associatedDataset = $contract?->dataset ?? ($import->selected_table === $table['name'] ? $import->dataset : null);
                                    $sourceSrid = (int) data_get($table, 'geometries.0.srid', 0);
                                @endphp
                                <div class="overflow-hidden rounded-xl border border-slate-200">
                                    <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 p-3"><div><p class="font-semibold">{{ $table['name'] }} @if($associatedDataset)<span class="ml-2 text-xs font-normal text-emerald-700">{{ $contract?->status === \App\Enums\SpatialImportStatus::Approved || ($contract === null && $import->status === \App\Enums\SpatialImportStatus::Approved) ? 'Aprobada' : 'En revisión' }}</span>@else<span class="ml-2 text-xs font-normal text-amber-700">Sin incorporar</span>@endif</p><p class="text-xs text-slate-500">{{ $table['row_count'] }} registros · {{ count($table['columns']) }} columnas @if($table['geometries']) · {{ $table['geometries'][0]['type'] }} / EPSG:{{ $table['geometries'][0]['srid'] }}@endif</p></div>@if($associatedDataset)<a class="btn-secondary" href="{{ route('admin.spatial-datasets.index').'#dataset-'.$associatedDataset->slug }}">Revisar conjunto</a>@endif</div>
                                    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead><tr class="text-left text-xs uppercase text-slate-500"><th class="p-3">Columna</th><th class="p-3">Tipo PostgreSQL</th><th class="p-3">Vacíos</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($table['columns'] as $column)<tr><td class="p-3 font-mono">{{ $column['name'] }}</td><td class="p-3">{{ $column['data_type'] }}</td><td class="p-3">{{ $column['nullable'] ? 'Permitidos' : 'No permitidos' }}</td></tr>@endforeach</tbody></table></div>
                                    @if(!$associatedDataset && $table['geometries'] && $sourceSrid <= 0)
                                        <p class="border-t border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Esta capa llegó sin sistema de coordenadas identificado (EPSG:0). Identifique el CRS real en QGIS y expórtela a esta misma zona con un nombre nuevo, por ejemplo <code>drenaje_doble_4326</code> si realmente corresponde a EPSG:4326. No basta con cambiar la etiqueta si las coordenadas son de otro sistema; la tabla anterior puede quedar bloqueada para reemplazo.</p>
                                    @elseif(!$associatedDataset && in_array($import->status, [\App\Enums\SpatialImportStatus::Profiled, \App\Enums\SpatialImportStatus::ContractDraft, \App\Enums\SpatialImportStatus::Approved], true))
                                        <form method="post" action="{{ route('admin.spatial-imports.contract.store', $import) }}" class="grid gap-3 border-t border-slate-200 bg-indigo-50/40 p-4 sm:grid-cols-2 lg:grid-cols-3" data-slug-suggestion>
                                            @php($suggestedName = $import->spatial_dataset_id ? \Illuminate\Support\Str::headline($table['name']) : $import->name)
                                            @csrf<input type="hidden" name="table" value="{{ $table['name'] }}"><label class="field"><span>Nombre institucional</span><input name="name" value="{{ \Illuminate\Support\Str::limit($suggestedName, 120, '') }}" required maxlength="120" data-slug-source></label><label class="field"><span>Identificador estable</span><input name="slug" value="{{ \Illuminate\Support\Str::slug($suggestedName) }}" required maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="limites-departamentales" data-slug-target><small>Se sugiere automáticamente desde el nombre. Puede editarlo antes de guardar.</small></label><label class="field"><span>Coordenadas para guardar en PostGIS</span><select name="storage_srid" required>@foreach($storageCrss as $srid => $label)<option value="{{ $srid }}" @selected((int) old('storage_srid', in_array($sourceSrid, array_keys($storageCrss), true) ? $sourceSrid : 9377) === $srid)>{{ $label }}</option>@endforeach</select><small>Origen de esta capa: EPSG:{{ $sourceSrid ?: 'sin identificar' }}. El visor transformará a WGS 84.</small></label><label class="field sm:col-span-2"><span>Descripción</span><input name="description" value="{{ $import->purpose }}" maxlength="2000"></label><button class="btn-primary sm:col-span-2 lg:col-span-3" onclick="return confirm('¿Incorporar esta capa como conjunto independiente para revisión?')">Incorporar esta capa</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @elseif($import->status !== \App\Enums\SpatialImportStatus::Failed)
                        <p class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">Aún no hay un perfil. Exporte la capa desde QGIS y pulse “Analizar”.</p>
                    @endif
                    <p class="text-xs text-slate-600">Cada capa incorporada crea su propio conjunto de datos y necesita aprobación antes de mostrarse en un geovisor. Puede seguir utilizando esta misma zona y usuario de QGIS para cargas posteriores.</p>
                </div>
            </details>
        @empty
            <div class="panel text-sm text-slate-500">Aún no hay importaciones autorizadas.</div>
        @endforelse
    </section>
</div>
@endsection
