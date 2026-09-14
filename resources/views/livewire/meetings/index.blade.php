<div>
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="eyebrow">Operación</p><h1 class="page-title">Reuniones</h1><p class="page-subtitle">Grabaciones, borradores y actas aprobadas.</p></div>
        <a href="{{ route('meetings.create') }}" class="btn-primary">Subir audio</a>
    </div>
    <div class="mt-7 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_240px]">
        <input wire:model.live.debounce.300ms="search" placeholder="Buscar por título...">
        <select wire:model.live="status"><option value="">Todos los estados</option>@foreach(\App\Enums\MeetingStatus::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach</select>
    </div>
    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="divide-y divide-slate-100">
            @forelse($meetings as $meeting)
                <a href="{{ route('meetings.show', $meeting) }}" class="grid gap-3 p-5 transition hover:bg-slate-50 sm:grid-cols-[1fr_190px_170px] sm:items-center">
                    <div><h2 class="font-semibold">{{ $meeting->title }}</h2><p class="mt-1 text-sm text-slate-500">{{ $meeting->meeting_type }} · {{ $meeting->held_at->format('d/m/Y H:i') }} · {{ $meeting->user->name }}</p></div>
                    <span class="status status-{{ $meeting->status->value }}">{{ $meeting->status->label() }}</span>
                    <span class="text-sm text-slate-500">Drive: {{ str_replace('_', ' ', $meeting->drive_sync_status->value) }}</span>
                </a>
            @empty<div class="p-10 text-center text-slate-500">Aún no hay reuniones.</div>@endforelse
        </div>
    </div>
    <div class="mt-5">{{ $meetings->links() }}</div>
</div>
