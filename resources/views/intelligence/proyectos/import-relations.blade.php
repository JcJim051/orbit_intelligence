@extends('layouts.app', ['title' => 'Importar relaciones desde proyectos · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="eyebrow">Seguimiento a metas</p>
            <h1 class="page-title">Importar relaciones desde proyectos</h1>
            <p class="page-subtitle max-w-4xl">Carga masiva para vincular BPIN con dependencias y metas producto. SIID crea internamente una única actividad técnica por meta para simplificar el reporte.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <a class="btn-secondary" href="{{ $projectsUrl }}">Volver a proyectos</a>
            <a class="btn-secondary" href="{{ $metasProductoUrl }}">Ver metas de producto</a>
        </div>
    </header>

    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <p class="font-semibold">Revise la matriz antes de volver a cargarla.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="panel border-emerald-100 bg-emerald-50/40">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)] lg:items-start">
            <div>
                <h2 class="text-xl font-semibold">Carga masiva de relaciones desde proyectos</h2>
                <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-600">
                    Descargue una matriz prediligenciada con los BPIN existentes, complete dependencia, meta producto y, si aplica,
                    metas producto. La importación vincula sin borrar relaciones existentes y crea/actualiza una actividad técnica consolidada por cada proyecto, dependencia y meta.
                </p>

                <ol class="mt-5 grid gap-3 text-sm text-slate-700">
                    <li class="rounded-xl border border-emerald-100 bg-white/80 p-4">
                        <strong class="block text-slate-950">1. Descargue la matriz prediligenciada</strong>
                        <span class="mt-1 block text-slate-500">La plantilla sale con los BPIN existentes para que el equipo complete solo las columnas necesarias.</span>
                    </li>
                    <li class="rounded-xl border border-emerald-100 bg-white/80 p-4">
                        <strong class="block text-slate-950">2. Complete las relaciones</strong>
                        <span class="mt-1 block text-slate-500">Diligencie dependencia, meta producto y cantidad programada física si la conoce.</span>
                    </li>
                    <li class="rounded-xl border border-emerald-100 bg-white/80 p-4">
                        <strong class="block text-slate-950">3. Cargue el archivo</strong>
                        <span class="mt-1 block text-slate-500">SIID vincula sin borrar relaciones existentes y mantiene una sola actividad técnica por meta.</span>
                    </li>
                </ol>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <a class="btn-secondary w-full" href="{{ route('intelligence.proyectos-metas.relations.template') }}">Descargar matriz prediligenciada</a>

                <form method="post" action="{{ route('intelligence.proyectos-metas.relations.import') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf
                    <label class="field">
                        <span>Matriz de relaciones de proyectos</span>
                        <input type="file" name="archivo_relaciones" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                        <small>Formato permitido: .xlsx. Tamaño máximo: 10 MB.</small>
                    </label>
                    <button class="btn-primary w-full">Cargar relaciones</button>
                </form>
            </div>
        </div>
    </section>
</div>
@endsection
