<x-mail::message>
# Estado de sus solicitudes

Estas son las solicitudes registradas con este correo. Use el número de seguimiento para consultarlas. No comparta ese número.

@foreach ($solicitudes as $solicitud)
- **{{ $solicitud->numero_seguimiento }}** · {{ $solicitud->secretariat }} · {{ $solicitud->status->label() }}
@endforeach

<x-mail::button :url="route('banks.status.create')">
Consultar estado
</x-mail::button>
</x-mail::message>
