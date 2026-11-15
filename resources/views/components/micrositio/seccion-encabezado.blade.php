@props(['titulo', 'bajada' => null, 'enlace' => null, 'url' => null])
<div class="sechead">
    <div>
        <h2>{{ $titulo }}</h2>
        @if ($bajada)
            <p class="sub">{{ $bajada }}</p>
        @endif
    </div>
    @if ($enlace && $url)
        <a class="more" href="{{ $url }}">{{ $enlace }}</a>
    @endif
</div>
