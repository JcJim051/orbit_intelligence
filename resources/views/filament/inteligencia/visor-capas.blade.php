<x-filament-panels::page>
    <div class="mapa-primero">
        <div wire:ignore class="mapa-lienzo" data-visor-capas>
            <script type="application/json" class="mapa-estado">@json(['capas' => $capas])</script>
        </div>

        <aside class="mapa-panel">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Meta</p>
            <h2 class="text-lg font-semibold text-slate-950">Visor de capas</h2>
            <p class="mt-1 text-sm text-slate-600">Las entidades salen de la copia en PostGIS, recortadas al área visible. No se inventan geometrías.</p>

            <ul class="mt-4 space-y-3">
                @foreach ($capas as $capa)
                    <li class="rounded-lg border border-slate-200 p-3 text-sm">
                        <label class="flex items-start gap-2">
                            <input type="checkbox" class="mt-1" data-capa-toggle value="{{ $capa['id'] }}" @checked($capa['activa'])>
                            <span>
                                <span class="inline-block h-3 w-3 rounded-full align-middle" style="background: {{ $capa['color'] }}"></span>
                                <span class="font-semibold">{{ $capa['etiqueta'] }}</span>
                            </span>
                        </label>
                        <p class="mt-2 text-slate-600">Fuente: {{ $capa['fuente'] ?: 'Sin entidad registrada' }}</p>
                        <p class="mt-1">
                            <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-800">{{ $capa['frescura'] }}</span>
                        </p>
                        <p class="mt-2">Última actualización: {{ $capa['ultima'] }}</p>
                        <p>TTL: {{ $capa['ttl'] }} h</p>
                        <p>Próxima según cadencia: {{ $capa['proxima'] }}</p>
                        <p class="mt-1 text-xs text-slate-500" title="{{ $capa['aviso'] }}">{{ $capa['aviso'] }}</p>
                        @if ($capa['pendiente'])
                            <p class="mt-2 font-medium text-amber-800">Pendiente de conector</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </aside>
    </div>
</x-filament-panels::page>
