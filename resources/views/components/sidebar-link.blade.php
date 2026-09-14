@props(['href', 'active' => false, 'badge' => null])

<a href="{{ $href }}" {{ $attributes->class(['app-nav-item', 'is-active' => $active]) }} @if($active) aria-current="page" @endif>
    @if($badge)<span class="app-nav-badge" aria-hidden="true">{{ $badge }}</span>@endif
    <span>{{ $slot }}</span>
</a>
