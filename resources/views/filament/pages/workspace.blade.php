<x-filament-panels::page>
    <div class="siid-legacy-workspace" data-management-workspace-base="{{ url('/gestion/espacios') }}">
        @if ($workspace === 'acta')
            <livewire:meetings.show :meeting="$meeting" />
        @else
            {{ $legacyContent }}
        @endif
    </div>
</x-filament-panels::page>
