@if($summary->status !== 'approved')> **BORRADOR — NO ES UN ACTA APROBADA**

@endif
# {{ $meeting->title }}

- **Fecha:** {{ $meeting->held_at->format('d/m/Y H:i') }}
- **Tipo:** {{ $meeting->meeting_type }}
- **Versión:** {{ $summary->version }}
- **Estado:** {{ $summary->status }}

## Resumen ejecutivo

{{ $summary->executive_summary }}

## Temas tratados
@foreach($summary->topics as $item)
- {{ $item }}
@endforeach

## Decisiones
@foreach($summary->decisions as $item)
- {{ $item }}
@endforeach

## Compromisos
@foreach($summary->actionItems->reject(fn($item) => $item->status === \App\Enums\ActionItemStatus::Rejected) as $item)
- [{{ $item->status === \App\Enums\ActionItemStatus::Approved ? 'x' : ' ' }}] {{ $item->description }}@if($item->owner_name) — **{{ $item->owner_name }}**@endif @if($item->due_date) ({{ $item->due_date->format('d/m/Y') }})@endif
@endforeach

## Riesgos o bloqueos
@foreach($summary->risks as $item)
- {{ $item }}
@endforeach

## Preguntas pendientes
@foreach($summary->pending_questions as $item)
- {{ $item }}
@endforeach
