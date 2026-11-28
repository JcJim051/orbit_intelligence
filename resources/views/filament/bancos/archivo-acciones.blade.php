<span class="banco-archivo">
    <span class="banco-archivo-nombre">{{ $archivo->original_filename }}</span>
    <span class="banco-archivo-acciones">
        @if ($archivo->esPdf())
            <button
                type="button"
                data-pdf-preview="{{ route('banks.files.download', ['archivo' => $archivo, 'inline' => 1]) }}"
                data-document-name="{{ $archivo->original_filename }}"
                data-download-url="{{ route('banks.files.download', $archivo) }}"
            >Vista previa</button>
        @endif
        <a href="{{ route('banks.files.download', $archivo) }}">Descargar</a>
    </span>
</span>
