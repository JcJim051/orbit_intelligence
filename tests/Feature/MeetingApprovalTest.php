<?php

namespace Tests\Feature;

use App\Enums\ActionItemStatus;
use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Jobs\PublishMeetingJob;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingSummary;
use App\Models\User;
use App\Services\MeetingApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MeetingApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_actions_must_be_classified_before_approval(): void
    {
        Queue::fake();
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $meeting = Meeting::create([
            'user_id' => $reviewer->id, 'reviewer_id' => $reviewer->id, 'title' => 'Acta', 'held_at' => now(), 'meeting_type' => 'Comité',
            'participants' => [], 'recording_consent_confirmed' => true, 'consent_confirmed_at' => now(),
            'artifacts' => ['minutes_pdf'], 'drive_connection_ids' => [], 'idempotency_key' => 'approval-0001', 'status' => MeetingStatus::PendingReview,
        ]);
        $summary = MeetingSummary::create([
            'meeting_id' => $meeting->id, 'version' => 1, 'status' => 'draft', 'executive_summary' => 'Resumen',
            'topics' => [], 'decisions' => [], 'risks' => [], 'pending_questions' => [], 'provider' => 'fake', 'model' => 'fake', 'prompt_version' => 'v1',
        ]);
        $action = ActionItem::create(['meeting_summary_id' => $summary->id, 'description' => 'Hacer seguimiento', 'status' => ActionItemStatus::Draft]);

        try {
            app(MeetingApprovalService::class)->approve($meeting, $reviewer);
            $this->fail('La aprobación debió ser rechazada.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $action->update(['status' => ActionItemStatus::Approved]);
        app(MeetingApprovalService::class)->approve($meeting->fresh(), $reviewer);
        $this->assertEquals(MeetingStatus::Approved, $meeting->fresh()->status);
        Queue::assertPushed(PublishMeetingJob::class);
    }
}
