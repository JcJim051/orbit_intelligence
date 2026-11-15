@props(['aliados'])
<div class="allies">
    @foreach ($aliados as $aliado)
        @if ($aliado['url'])
            <a class="ally" href="{{ $aliado['url'] }}" @if (str_starts_with($aliado['url'], 'http')) target="_blank" rel="noopener" @endif>
                @if ($aliado['logo_url'])
                    <img src="{{ $aliado['logo_url'] }}" alt="{{ $aliado['nombre'] }}">
                @else
                    {{ $aliado['nombre'] }}
                @endif
            </a>
        @else
            <div class="ally">
                @if ($aliado['logo_url'])
                    <img src="{{ $aliado['logo_url'] }}" alt="{{ $aliado['nombre'] }}">
                @else
                    {{ $aliado['nombre'] }}
                @endif
            </div>
        @endif
    @endforeach
</div>
