{{-- Valor de un conteo del listado. Espera: $catalog, $record, $relation, $label, $countValue. --}}
@php($countDetailable = $countValue > 0 && in_array($relation, $catalog->countDetails(), true) && \Illuminate\Support\Facades\Route::has($catalog->routeName().'.detail'))
@php($countPreview = $countValue > 0 ? $catalog->countPreview($record, $relation, $countValue) : null)
@if($countDetailable)
    <button type="button"
            class="count-detail-trigger font-semibold text-indigo-700"
            data-count-detail-url="{{ route($catalog->routeName().'.detail', [$catalog->routeParameter() => $record, 'relacion' => $relation]) }}"
            aria-haspopup="dialog"
            aria-controls="count-detail-dialog"
            title="{{ $countPreview['title'] ?? 'Ver '.mb_strtolower($label) }}">{{ $countPreview['label'] ?? $countValue }}</button>
@elseif($countPreview)
    <span title="{{ $countPreview['title'] }}">{{ $countPreview['label'] }}</span>
@else
    {{ $countValue }}
@endif
