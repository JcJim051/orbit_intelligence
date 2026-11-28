<x-mail::message>
# Estado de su solicitud

La solicitud **{{ $solicitud->numero_seguimiento }}** de **{{ $solicitud->secretariat }}** ahora está **{{ $solicitud->status->label() }}**.

@if ($observacion)
{{ $observacion }}
@endif

@if ($solicitud->status->value === 'devuelto')
Abra la consulta, escriba el número de seguimiento y cargue de nuevo el formato. El número no cambia.
@endif

<x-mail::button :url="route('banks.status.create')">
Consultar estado
</x-mail::button>
</x-mail::message>
