@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <section class="overflow-hidden rounded-[2rem] border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-slate-50 p-8 shadow-sm">
        <div class="grid gap-8 lg:grid-cols-[1fr,260px] lg:items-start">
            <div>
                <p class="eyebrow">Indicador SIID 2.0</p>
                <h1 class="mt-3 max-w-4xl text-4xl font-black tracking-tight text-slate-950 md:text-5xl">{{ $indicator->name }}</h1>
                @if($indicator->summary)
                    <p class="mt-5 max-w-3xl text-lg leading-8 text-slate-700">{{ $indicator->summary }}</p>
                @endif
                <div class="mt-6 flex flex-wrap gap-3">
                    @if($indicator->sector)<span class="badge">{{ $indicator->sector }}</span>@endif
                    @if($indicator->unit)<span class="badge">Unidad: {{ $indicator->unit }}</span>@endif
                    @if($indicator->periodicity)<span class="badge">Periodicidad: {{ $indicator->periodicity }}</span>@endif
                    @if($indicator->published_at)<span class="badge">Publicado {{ $indicator->published_at->format('d/m/Y') }}</span>@endif
                </div>
            </div>

            <aside class="rounded-3xl border border-emerald-100 bg-white/85 p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">QR del indicador</p>
                <img class="mt-4 aspect-square w-full rounded-2xl bg-white p-3" src="{{ $qrCode }}" alt="Código QR para abrir {{ $indicator->name }}">
                <p class="mt-3 text-sm text-slate-600">Use este código en piezas impresas, videos o publicaciones para traer tráfico directo a este indicador.</p>
            </aside>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1fr,360px]">
        <article class="panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Ficha técnica oficial</p>
                    <h2>Documento metodológico</h2>
                </div>
                @if($indicator->hasTechnicalSheet())
                    <a class="btn-primary" href="{{ route('indicators.technical-sheet', $indicator) }}" target="_blank" rel="noopener">Ver PDF</a>
                @endif
            </div>

            @if($indicator->description)
                <div class="prose max-w-none text-slate-700">
                    {!! nl2br(e($indicator->description)) !!}
                </div>
            @else
                <p class="text-slate-600">Consulte la ficha técnica oficial para conocer la metodología, alcance y fuente del indicador.</p>
            @endif
        </article>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Explorar</p>
                    <h2>Otros datos publicados</h2>
                </div>
            </div>

            @if($otherIndicators->isNotEmpty())
                <div class="space-y-3">
                    @foreach($otherIndicators as $other)
                        <a href="{{ route('indicators.show', $other) }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-emerald-300 hover:shadow-sm">
                            <strong class="text-slate-950">{{ $other->name }}</strong>
                            <span class="mt-1 block text-sm text-slate-600">{{ $other->sector ?: 'Indicador publicado' }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-600">Este será el punto de navegación hacia los demás indicadores publicados cuando existan.</p>
            @endif
        </aside>
    </section>
</div>
@endsection
