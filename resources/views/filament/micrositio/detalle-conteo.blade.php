<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr>
                @foreach ($columnas as $columna)
                    <th class="border-b px-3 py-2 text-left font-semibold">{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    @foreach ($fila as $celda)
                        <td class="border-b px-3 py-2 align-top">{{ $celda }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="px-3 py-4 text-gray-500" colspan="{{ max(count($columnas), 1) }}">No hay registros en este conteo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
