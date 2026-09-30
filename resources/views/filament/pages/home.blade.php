<x-filament-panels::page>
    <section class="siid-hero">
        <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-700">Gobernación del Meta · SIID 2.0</p>
        <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Hola, {{ auth()->user()->name }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base">Este es su espacio de trabajo. Los módulos, pendientes y publicaciones se adaptan a sus responsabilidades.</p>
            </div>
            <span class="rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800">{{ auth()->user()->role->label() }}</span>
        </div>
    </section>

    <section aria-labelledby="resumen-heading">
        <div class="mb-3">
            <h2 id="resumen-heading" class="text-lg font-semibold text-slate-950">Resumen de trabajo</h2>
            <p class="text-sm text-slate-500">Información priorizada según su rol.</p>
        </div>
        <div class="siid-stat-grid">
            @foreach($stats as $stat)
                <article class="siid-stat">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ number_format($stat['value'], 0, ',', '.') }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="modules-heading">
        <div class="mb-3">
            <h2 id="modules-heading" class="text-lg font-semibold text-slate-950">Módulos disponibles</h2>
            <p class="text-sm text-slate-500">Cada espacio conserva su flujo y responsabilidades.</p>
        </div>
        <div class="siid-module-grid">
            @foreach($modules as $module)
                <a @class(['siid-module-card', 'pointer-events-none opacity-65' => ! ($module['available'] ?? true)])
                   href="{{ $module['url'] ?? '#' }}"
                   @if(! ($module['available'] ?? true)) aria-disabled="true" tabindex="-1" @endif
                   style="--module-color: {{ $module['color'] }}; --module-soft: {{ $module['soft'] }}">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <span class="siid-module-icon" aria-hidden="true">{{ $module['code'] }}</span>
                            @if(! ($module['available'] ?? true))
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">Requiere migración</span>
                            @elseif($module['comingSoon'] ?? false)
                                <span class="rounded-full bg-pink-50 px-2.5 py-1 text-xs font-semibold text-pink-700">Próximamente</span>
                            @endif
                        </div>
                        <h3 class="mt-5 text-lg font-semibold text-slate-950">{{ $module['name'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $module['description'] }}</p>
                    </div>
                    <span class="text-sm font-semibold" style="color: {{ $module['color'] }}">{{ ($module['available'] ?? true) ? 'Abrir módulo' : 'Pendiente de instalación' }} <span aria-hidden="true">→</span></span>
                </a>
            @endforeach
        </div>
    </section>

    @if($activity->isNotEmpty())
        <section aria-labelledby="activity-heading" class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 id="activity-heading" class="text-lg font-semibold text-slate-950">Actividad reciente</h2>
            <div class="mt-4 divide-y divide-slate-100">
                @foreach($activity as $entry)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                        <span class="font-medium text-slate-800">{{ str($entry->event)->replace('_', ' ')->headline() }}</span>
                        <time class="text-slate-500" datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->diffForHumans() }}</time>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-filament-panels::page>
