@props(['texto' => null, 'tipo' => 'neutro'])
@php
    $clase = match ($tipo) {
        'sube_bueno' => 'up',
        'baja_malo' => 'down',
        default => 'neu',
    };
@endphp
@if ($texto)
    <span class="delta {{ $clase }}">{{ $texto }}</span>
@endif
