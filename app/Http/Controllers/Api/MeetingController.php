<?php

namespace App\Http\Controllers\Api;

use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeetingRequest;
use App\Http\Resources\MeetingResource;
use App\Jobs\AnalyzeMeetingJob;
use App\Jobs\PrepareMeetingAudioJob;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Services\AuditLogger;
use App\Services\MeetingIngestionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MeetingController extends Controller
{
    public function index(Request $request)
    {
        $query = Meeting::query()->latest('held_at');
        if (! $request->user()->isAdmin()) {
            $query->where(fn ($q) => $q->where('user_id', $request->user()->id)->orWhere('reviewer_id', $request->user()->id));
        }

        return MeetingResource::collection($query->paginate(25));
    }

    public function store(StoreMeetingRequest $request, MeetingIngestionService $service)
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || mb_strlen($key) < 8 || mb_strlen($key) > 100) {
            throw ValidationException::withMessages(['idempotency_key' => 'Envía una cabecera Idempotency-Key de 8 a 100 caracteres.']);
        }
        $meeting = $service->ingest($request->user(), $request->file('audio'), $request->validated(), $key);

        return (new MeetingResource($meeting))->response()->setStatusCode(202);
    }

    public function show(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        return new MeetingResource($meeting);
    }

    public function retry(Request $request, Meeting $meeting, AuditLogger $audit)
    {
        $this->authorize('update', $meeting);
        abort_unless($meeting->status === MeetingStatus::Error, 422, 'La reunión no está en error.');
        $job = match ($meeting->error_stage) {
            'transcription' => new TranscribeMeetingJob($meeting->id),
            'analysis' => new AnalyzeMeetingJob($meeting->id),
            default => new PrepareMeetingAudioJob($meeting->id),
        };
        $meeting->update(['status' => $meeting->error_stage === 'analysis' ? MeetingStatus::Transcribing : MeetingStatus::PendingTranscription, 'error_message' => null]);
        dispatch($job);
        $audit->log($meeting, 'processing.retried', $request->user(), [], $meeting->error_stage);

        return new MeetingResource($meeting->fresh());
    }
}
