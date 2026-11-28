<x-mail::message>
# Solicitud recibida

Recibimos el formato de **{{ $solicitud->secretariat }}**.

Su número de seguimiento es **{{ $solicitud->numero_seguimiento }}**.

<x-mail::button :url="route('banks.status.create')">
Consultar estado
</x-mail::button>

Guarde este número. Con él puede consultar el estado y, si Planeación devuelve el formato, enviar la corrección en la misma solicitud.
</x-mail::message>
