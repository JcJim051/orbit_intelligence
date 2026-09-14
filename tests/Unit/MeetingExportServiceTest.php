<?php

namespace Tests\Unit;

use App\Models\Meeting;
use App\Models\MeetingSummary;
use App\Models\User;
use App\Services\MeetingExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_markdown_marks_unapproved_summary_as_draft(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::create([
            'user_id' => $user->id, 'title' => 'Prueba', 'held_at' => now(), 'meeting_type' => 'Comité',
            'participants' => [], 'recording_consent_confirmed' => true, 'consent_confirmed_at' => now(),
            'artifacts' => ['minutes_markdown'], 'drive_connection_ids' => [], 'idempotency_key' => 'export-0001',
        ]);
        $summary = MeetingSummary::create([
            'meeting_id' => $meeting->id, 'version' => 1, 'status' => 'draft', 'executive_summary' => 'Resumen',
            'topics' => ['Tema'], 'decisions' => [], 'risks' => [], 'pending_questions' => [],
            'provider' => 'fake', 'model' => 'fake', 'prompt_version' => 'v1',
        ]);
        $markdown = app(MeetingExportService::class)->markdown($meeting, $summary->load('actionItems'));
        $this->assertStringContainsString('BORRADOR', $markdown);
        $this->assertStringContainsString('# Prueba', $markdown);
    }
}
