<?php

namespace Tests\Feature\Investment;

use App\Enums\MeetingStatus;
use App\Models\InvestmentProject;
use App\Models\Meeting;
use App\Models\MeetingSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestmentMeetingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_adds_project_to_agenda_and_creates_draft_commitment(): void
    {
        $user = User::factory()->create();
        $meeting = $this->meetingFor($user);
        $summary = MeetingSummary::create([
            'meeting_id' => $meeting->id, 'version' => 1, 'status' => 'draft', 'executive_summary' => '',
            'topics' => [], 'decisions' => [], 'risks' => [], 'pending_questions' => [],
            'provider' => 'fake', 'model' => 'fake', 'prompt_version' => 'v1',
        ]);
        $project = InvestmentProject::factory()->create();

        $this->actingAs($user)->post(route('investments.meetings.store', $project), [
            'meeting_id' => $meeting->id, 'agenda_reason' => 'Revisar retrasos', 'prepared_questions' => "¿Cuál es el avance?\n¿Cuál es el bloqueo?",
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('investment_project_meeting', ['meeting_id' => $meeting->id, 'investment_project_id' => $project->id, 'agenda_reason' => 'Revisar retrasos']);
        $this->actingAs($user)->post(route('investments.actions.store', $project), [
            'meeting_id' => $meeting->id, 'description' => 'Enviar cronograma actualizado', 'owner_name' => 'Secretaría', 'due_date' => '2026-10-01',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('action_items', [
            'meeting_summary_id' => $summary->id, 'investment_project_id' => $project->id,
            'description' => 'Enviar cronograma actualizado', 'status' => 'draft',
        ]);
        $this->actingAs($user)->post(route('investments.decisions.store', $project), [
            'meeting_id' => $meeting->id, 'decision' => 'Solicitar mesa técnica de seguimiento',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('project_decisions', [
            'meeting_summary_id' => $summary->id, 'investment_project_id' => $project->id,
            'decision' => 'Solicitar mesa técnica de seguimiento',
        ]);

        $this->actingAs($user)->get(route('meetings.show', $meeting))->assertOk()->assertSee($project->bpin)->assertSee('Solicitar mesa técnica de seguimiento');
        $this->actingAs($user)->get(route('investments.projects.show', $project))->assertOk()->assertSee('Enviar cronograma actualizado')->assertSee('Solicitar mesa técnica de seguimiento');
    }

    public function test_another_member_cannot_add_project_to_private_meeting(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $meeting = $this->meetingFor($owner);
        $project = InvestmentProject::factory()->create();

        $this->actingAs($other)->post(route('investments.meetings.store', $project), [
            'meeting_id' => $meeting->id, 'agenda_reason' => 'Intento no autorizado',
        ])->assertForbidden();

        $this->assertDatabaseMissing('investment_project_meeting', ['meeting_id' => $meeting->id, 'investment_project_id' => $project->id]);
    }

    private function meetingFor(User $user): Meeting
    {
        return Meeting::create([
            'user_id' => $user->id, 'title' => 'Seguimiento de inversión', 'held_at' => now(), 'meeting_type' => 'Seguimiento',
            'participants' => [], 'recording_consent_confirmed' => true, 'consent_confirmed_at' => now(),
            'artifacts' => ['transcript'], 'drive_connection_ids' => [], 'idempotency_key' => fake()->uuid(),
            'status' => MeetingStatus::PendingReview,
        ]);
    }
}
