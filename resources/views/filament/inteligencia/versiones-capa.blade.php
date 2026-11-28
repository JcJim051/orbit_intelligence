<div class="overflow-x-auto text-sm">
    <table class="w-full text-left">
        <thead>
            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <th class="px-2 py-2">Versión</th>
                <th class="px-2 py-2">Corte</th>
                <th class="px-2 py-2">Conteo</th>
                <th class="px-2 py-2">Agregados</th>
                <th class="px-2 py-2">Modificados</th>
                <th class="px-2 py-2">Eliminados</th>
                <th class="px-2 py-2">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($versiones as $version)
                <tr class="border-b border-slate-100">
                    <td class="px-2 py-2">{{ $version->numero }}</td>
                    <td class="px-2 py-2">{{ $version->fecha_corte?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                    <td class="px-2 py-2">{{ $version->conteo }}</td>
                    <td class="px-2 py-2">{{ $version->agregados }}</td>
                    <td class="px-2 py-2">{{ $version->modificados }}</td>
                    <td class="px-2 py-2">{{ $version->eliminados }}</td>
                    <td class="px-2 py-2">{{ $version->vigente ? 'Vigente' : 'Archivada' }}</td>
                </tr>
            @empty
                <tr>
                    <td class="px-2 py-2" colspan="7">Esta capa todavía no tiene versiones.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
