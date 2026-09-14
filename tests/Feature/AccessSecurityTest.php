<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_inactive_user_cannot_use_the_panel(): void
    {
        $user = User::factory()->create(['active' => false]);
        $this->actingAs($user)->get('/meetings')->assertForbidden();
    }

    public function test_owner_can_render_the_meeting_detail(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::create([
            'user_id' => $user->id,
            'title' => 'Detalle visible',
            'held_at' => now(),
            'meeting_type' => 'Reunión',
            'participants' => [],
            'recording_consent_confirmed' => true,
            'consent_confirmed_at' => now(),
            'artifacts' => ['transcript'],
            'drive_connection_ids' => [],
            'idempotency_key' => 'detail-render-0001',
        ]);

        $this->actingAs($user)
            ->get(route('meetings.show', $meeting))
            ->assertOk()
            ->assertSee('Audio original');
    }
}
