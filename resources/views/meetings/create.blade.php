@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <p class="eyebrow">Nueva reunión</p><h1 class="page-title">Subir grabación</h1>
    <form method="post" action="{{ route('meetings.store') }}" enctype="multipart/form-data" class="mt-7 space-y-7 rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">@csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <label class="field sm:col-span-2"><span>Archivo de audio</span><input type="file" name="audio" accept="audio/*,.m4a,.mp4,.webm" required><small>Hasta 500 MB y 2 horas.</small></label>
            <label class="field sm:col-span-2"><span>Título</span><input name="title" value="{{ old('title') }}" required></label>
            <label class="field"><span>Fecha y hora</span><input type="datetime-local" name="held_at" value="{{ old('held_at', now()->format('Y-m-d\TH:i')) }}" required></label>
            <label class="field"><span>Tipo de reunión</span><input name="meeting_type" list="meeting-types" value="{{ old('meeting_type') }}" required><datalist id="meeting-types"><option>Comité</option><option>Seguimiento</option><option>Gerencia</option><option>Proyecto</option></datalist></label>
            <label class="field sm:col-span-2"><span>Revisor</span><select name="reviewer_id"><option value="">Sin asignar</option>@foreach($reviewers as $reviewer)<option value="{{ $reviewer->id }}">{{ $reviewer->name }}</option>@endforeach</select></label>
        </div>
        <fieldset><legend class="font-semibold">Participantes opcionales</legend><div class="mt-3 grid gap-3 sm:grid-cols-2">@for($i=0;$i<4;$i++)<input name="participants[{{ $i }}][name]" placeholder="Nombre"><input type="email" name="participants[{{ $i }}][email]" placeholder="Correo opcional">@endfor</div></fieldset>
        <fieldset><legend class="font-semibold">Productos para Google Drive</legend><div class="mt-3 grid gap-3 sm:grid-cols-2">@foreach(['audio'=>'Audio original','transcript'=>'Transcripción','minutes_markdown'=>'Acta Markdown','minutes_pdf'=>'Acta PDF'] as $value=>$label)<label class="check"><input type="checkbox" name="artifacts[]" value="{{ $value }}" {{ $value !== 'audio' ? 'checked' : '' }}> {{ $label }}</label>@endforeach</div></fieldset>
        <fieldset><legend class="font-semibold">Destinos</legend><div class="mt-3 space-y-2">@forelse($connections as $connection)<label class="check"><input type="checkbox" name="drive_connection_ids[]" value="{{ $connection->id }}" checked> {{ $connection->label }} <span class="text-slate-400">{{ $connection->google_email }}</span></label>@empty<p class="text-sm text-amber-700">No hay Drives activos. El procesamiento continuará sin publicación.</p>@endforelse</div></fieldset>
        <label class="flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900"><input class="mt-1" type="checkbox" name="recording_consent_confirmed" value="1" required><span>Confirmo que las personas participantes fueron informadas y consintieron la grabación y su procesamiento.</span></label>
        <button class="btn-primary">Guardar y procesar</button>
    </form>
</div>
@endsection
