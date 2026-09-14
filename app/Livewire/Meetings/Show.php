<?php

namespace App\Livewire\Meetings;

use App\Enums\ActionItemStatus;
use App\Enums\MeetingStatus;
use App\Jobs\AnalyzeMeetingJob;
use App\Jobs\PrepareMeetingAudioJob;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Services\AuditLogger;
use App\Services\MeetingApprovalService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public Meeting $meeting;

    public string $executiveSummary = '';

    public string $topics = '';

    public string $decisions = '';

    public string $risks = '';

    public string $questions = '';

    public array $segments = [];

    public array $speakerMap = [];

    public array $actions = [];

    public function mount(Meeting $meeting): void
    {
        Gate::authorize('view', $meeting);
        $this->meeting = $meeting;
        $this->loadDraft();
    }

    public function loadDraft(): void
    {
        $this->meeting->refresh()->load(['transcript.segments', 'summaries.actionItems.investmentProject', 'summaries.projectDecisions.project', 'files', 'exports.connection', 'investmentProjects']);
        $summary = $this->meeting->currentSummary();
        $this->executiveSummary = $summary?->executive_summary ?? '';
        $this->topics = implode("\n", $summary?->topics ?? []);
        $this->decisions = implode("\n", $summary?->decisions ?? []);
        $this->risks = implode("\n", $summary?->risks ?? []);
        $this->questions = implode("\n", $summary?->pending_questions ?? []);
        $this->speakerMap = $this->meeting->transcript?->speaker_map ?? [];
        $this->segments = $this->meeting->transcript?->segments->map(fn ($s) => ['id' => $s->id, 'speaker_key' => $s->speaker_key, 'edited_text' => $s->edited_text, 'start_ms' => $s->start_ms])->all() ?? [];
        $this->actions = $summary?->actionItems->map(fn ($a) => [
            'id' => $a->id, 'description' => $a->description, 'owner_name' => $a->owner_name,
            'due_date' => $a->due_date?->format('Y-m-d'), 'due_date_text' => $a->due_date_text,
            'status' => $a->status->value,
            'investment_project_id' => $a->investment_project_id,
            'investment_project_bpin' => $a->investmentProject?->bpin,
            'investment_project_name' => $a->investmentProject?->name,
        ])->all() ?? [];
    }

    public function saveDraft(AuditLogger $audit): void
    {
        Gate::authorize('update', $this->meeting);
        abort_unless($this->meeting->status === MeetingStatus::PendingReview, 422);
        $summary = $this->meeting->currentSummary();
        $summary->update([
            'executive_summary' => $this->executiveSummary,
            'topics' => $this->lines($this->topics), 'decisions' => $this->lines($this->decisions),
            'risks' => $this->lines($this->risks), 'pending_questions' => $this->lines($this->questions),
        ]);
        if ($transcript = $this->meeting->transcript) {
            foreach ($this->segments as $segment) {
                $transcript->segments()->whereKey($segment['id'])->update(['edited_text' => $segment['edited_text']]);
            }
            $transcript->update([
                'edited_text' => $transcript->segments()->orderBy('sequence')->pluck('edited_text')->implode("\n"),
                'speaker_map' => $this->speakerMap,
            ]);
        }
        foreach ($this->actions as $action) {
            $summary->actionItems()->whereKey($action['id'])->update([
                'description' => $action['description'], 'owner_name' => $action['owner_name'] ?: null,
                'due_date' => $action['due_date'] ?: null, 'due_date_text' => $action['due_date_text'] ?: null,
            ]);
        }
        $audit->log($this->meeting, 'meeting.draft_updated', auth()->user(), ['version' => $summary->version]);
        session()->flash('status', 'Borrador guardado.');
        $this->loadDraft();
    }

    public function classify(string $id, string $status, AuditLogger $audit): void
    {
        Gate::authorize('update', $this->meeting);
        abort_unless($this->meeting->status === MeetingStatus::PendingReview, 422);
        $enum = ActionItemStatus::from($status);
        $item = $this->meeting->currentSummary()->actionItems()->whereKey($id)->firstOrFail();
        $item->update(['status' => $enum]);
        $audit->log($this->meeting, 'action_item.classified', auth()->user(), ['action_item_id' => $id, 'status' => $status]);
        $this->loadDraft();
    }

    public function approve(MeetingApprovalService $service): void
    {
        Gate::authorize('approve', $this->meeting);
        $service->approve($this->meeting, auth()->user());
        session()->flash('status', 'Acta aprobada. La publicación en Drive quedó en cola.');
        $this->loadDraft();
    }

    public function reopen(MeetingApprovalService $service): void
    {
        Gate::authorize('approve', $this->meeting);
        $service->reopen($this->meeting, auth()->user());
        session()->flash('status', 'Se creó una nueva versión editable.');
        $this->loadDraft();
    }

    public function retry(AuditLogger $audit): void
    {
        Gate::authorize('update', $this->meeting);
        abort_unless($this->meeting->status === MeetingStatus::Error, 422);
        $stage = $this->meeting->error_stage;
        $job = match ($stage) {
            'transcription' => new TranscribeMeetingJob($this->meeting->id),
            'analysis' => new AnalyzeMeetingJob($this->meeting->id),
            default => new PrepareMeetingAudioJob($this->meeting->id),
        };
        $this->meeting->update(['status' => $stage === 'analysis' ? MeetingStatus::Transcribing : MeetingStatus::PendingTranscription, 'error_message' => null]);
        dispatch($job);
        $audit->log($this->meeting, 'processing.retried', auth()->user(), [], $stage);
        $this->loadDraft();
    }

    private function lines(string $value): array
    {
        return collect(preg_split('/\R/', $value))->map(fn ($line) => trim($line))->filter()->values()->all();
    }

    public function render()
    {
        return view('livewire.meetings.show');
    }
}
