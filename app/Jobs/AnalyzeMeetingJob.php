<?php

namespace App\Jobs;

use App\Contracts\MeetingAnalysisProvider;
use App\Enums\MeetingStatus;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingSummary;
use App\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class AnalyzeMeetingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 900;

    public function __construct(public string $meetingId)
    {
        $this->onQueue('analysis');
    }

    public function handle(MeetingAnalysisProvider $provider, AuditLogger $audit): void
    {
        $meeting = Meeting::with('transcript')->findOrFail($this->meetingId);
        $audit->log($meeting, 'processing.started', null, ['provider' => $provider->name(), 'model' => $provider->model()], 'analysis');
        $result = $provider->analyze($meeting, $meeting->transcript);

        DB::transaction(function () use ($meeting, $provider, $result) {
            $version = ((int) $meeting->summaries()->max('version')) + 1;
            $summary = MeetingSummary::create([
                'meeting_id' => $meeting->id, 'version' => $version, 'status' => 'draft',
                'executive_summary' => $result->executiveSummary, 'topics' => $result->topics,
                'decisions' => $result->decisions, 'risks' => $result->risks,
                'pending_questions' => $result->pendingQuestions, 'provider' => $provider->name(),
                'model' => $provider->model(), 'prompt_version' => 'v1',
            ]);
            foreach ($result->actionItems as $item) {
                ActionItem::create([
                    'meeting_summary_id' => $summary->id,
                    'description' => $item['description'], 'owner_name' => $item['owner_name'] ?? null,
                    'due_date' => filled($item['due_date'] ?? null) ? $item['due_date'] : null,
                    'due_date_text' => $item['due_date_text'] ?? null,
                    'source_excerpt' => $item['source_excerpt'] ?? null, 'status' => 'draft',
                ]);
            }
            $meeting->update(['status' => MeetingStatus::PendingReview, 'error_stage' => null, 'error_message' => null]);
        });
        $audit->log($meeting, 'processing.completed', null, [], 'analysis');
    }

    public function failed(Throwable $e): void
    {
        $meeting = Meeting::find($this->meetingId);
        $meeting?->update(['status' => MeetingStatus::Error, 'error_stage' => 'analysis', 'error_message' => str($e->getMessage())->limit(500)]);
        if ($meeting) {
            app(AuditLogger::class)->log($meeting, 'processing.failed', null, ['message' => str($e->getMessage())->limit(500)->toString()], 'analysis');
        }
    }
}
