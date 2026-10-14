@extends('layouts.app', ['title' => 'Detalle indicador ODS · SIID 2.0'])

@php
    $meta = $relation->indicador?->metasResultado?->first();
    $pilar = $meta?->programa?->linea?->eje?->pilar
        ?? $meta?->subprograma?->programa?->linea?->eje?->pilar;
    $ods = $relation->odsIndicator;
@endphp

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <header>
        <a class="text-sm font-semibold text-indigo-700" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'ods-relaciones']) }}">← Indicadores ODS relacionados</a>
        <p class="eyebrow mt-5">Cadena de relaciones</p>
        <h1 class="page-title">{{ $ods?->code }} · {{ $ods?->name }}</h1>
        <p class="page-subtitle">Vista de consulta del indicador ODS y su contexto dentro del Plan de Desarrollo.</p>
    </header>

    <section class="panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <h2 class="text-lg font-semibold">Relación {{ $statusLabel }}</h2>
            <span class="status {{ $relation->status === 'accepted' ? 'status-approved' : '' }}">{{ $statusLabel }}</span>
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <article class="rounded-xl border border-slate-200 p-4"><p class="detail-label">Objetivo ODS</p><p class="detail-value">{{ $ods?->target?->goal?->code }} · {{ $ods?->target?->goal?->name }}</p></article>
            <article class="rounded-xl border border-slate-200 p-4"><p class="detail-label">Meta ODS</p><p class="detail-value">{{ $ods?->target?->code }} · {{ $ods?->target?->name }}</p></article>
            <article class="rounded-xl border border-slate-200 p-4"><p class="detail-label">Indicador ODS</p><p class="detail-value">{{ $ods?->code }} · {{ $ods?->name }}</p></article>
            <article class="rounded-xl border border-slate-200 p-4"><p class="detail-label">Pilar PDD</p><p class="detail-value">{{ $pilar?->codigo }} · {{ $pilar?->nombre ?? 'Sin relación' }}</p></article>
            <article class="rounded-xl border border-slate-200 p-4 md:col-span-2"><p class="detail-label">Meta resultado</p><p class="detail-value">{{ $meta?->codigo_provisional ?? $meta?->codigo }} · {{ $meta?->descripcion ?? 'Sin relación' }}</p></article>
            <article class="rounded-xl border border-slate-200 p-4 md:col-span-2"><p class="detail-label">Indicador resultado</p><p class="detail-value">{{ $relation->indicador?->codigo }} · {{ $relation->indicador?->nombre }}</p><p class="mt-1 text-sm text-slate-500">Unidad: {{ $relation->indicador?->unidad_medida ?? 'Sin unidad' }}</p></article>
        </div>
        <dl class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
            <div><dt class="detail-label">Tipo de relación</dt><dd class="detail-value">{{ \App\Http\Controllers\Intelligence\OdsIndicatorReviewController::RELATION_TYPES[$relation->relation_type] ?? $relation->relation_type }}</dd></div>
            <div><dt class="detail-label">Confianza</dt><dd class="detail-value">{{ \App\Http\Controllers\Intelligence\OdsIndicatorReviewController::CONFIDENCE[$relation->confidence] ?? $relation->confidence }}</dd></div>
        </dl>
        @if($relation->justification)
            <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600"><strong class="text-slate-800">Justificación:</strong> {{ $relation->justification }}</div>
        @endif
    </section>
</div>
<style>
    .detail-label { color: rgb(100 116 139); font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .detail-value { margin-top: .25rem; color: rgb(15 23 42); font-size: .95rem; }
</style>
@endsection
