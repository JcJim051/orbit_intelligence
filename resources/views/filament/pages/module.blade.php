<x-filament-panels::page>
    <section class="siid-hero">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-700">{{ $eyebrow }}</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ $this->getTitle() }}</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">{{ $description }}</p>
            </div>
            @if($status)<span class="rounded-full border border-pink-200 bg-pink-50 px-3 py-1.5 text-xs font-semibold text-pink-700">{{ $status }}</span>@endif
        </div>

        @if($actions)
            <div class="mt-6 flex flex-wrap gap-3">
                @foreach($actions as $action)
                    <a href="{{ $action['url'] }}" @class([
                        'inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition',
                        'bg-emerald-600 text-white hover:bg-emerald-700' => $action['primary'] ?? false,
                        'border border-slate-300 bg-white text-slate-700 hover:border-emerald-300 hover:text-emerald-700' => ! ($action['primary'] ?? false),
                    ])>{{ $action['label'] }}</a>
                @endforeach
            </div>
        @endif
    </section>

    @if($steps)
        <section aria-labelledby="workflow-heading">
            <div class="mb-3">
                <h2 id="workflow-heading" class="text-lg font-semibold text-slate-950">Flujo del módulo</h2>
                <p class="text-sm text-slate-500">Las etapas se presentan únicamente dentro de su contexto.</p>
            </div>
            <div class="siid-workflow">
                @foreach($steps as $step)
                    <div class="siid-workflow-step">
                        <strong>{{ $loop->iteration }}. {{ $step['title'] }}</strong>
                        <span>{{ $step['description'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-filament-panels::page>
