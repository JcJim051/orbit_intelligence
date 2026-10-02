{{-- Conteo clicable con el modal compartido de detalle (intelligence.catalogs.partials.count-detail-dialog). --}}
@props(['url', 'conteo', 'titulo' => 'Ver detalle'])
@if($conteo > 0)
    <button type="button"
            class="count-detail-trigger font-semibold text-indigo-700"
            data-count-detail-url="{{ $url }}"
            aria-haspopup="dialog"
            aria-controls="count-detail-dialog"
            title="{{ $titulo }}">{{ $conteo }}</button>
@else
    <span class="text-slate-400">0</span>
@endif
