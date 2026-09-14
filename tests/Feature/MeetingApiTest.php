<?php

namespace Tests\Feature;

use App\Contracts\AudioInspector;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        $this->app->bind(AudioInspector::class, fn () => new class implements AudioInspector
        {
            public function inspect(string $path): array
            {
                return ['duration_ms' => 60000, 'mime_type' => 'audio/mp4'];
            }
        });
    }

    public function test_iphone_can_upload_with_consent_and_idempotency(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone', ['meetings:upload'])->plainTextToken;
        $payload = [
            'audio' => UploadedFile::fake()->create('reunion.m4a', 128, 'audio/mp4'),
            'title' => 'Comité semanal', 'held_at' => now()->toIso8601String(), 'meeting_type' => 'Comité',
            'recording_consent_confirmed' => '1', 'artifacts' => ['transcript', 'minutes_pdf'],
        ];

        $this->withToken($token)->withHeader('Idempotency-Key', 'iphone-test-0001')->post('/api/v1/meetings', $payload)
            ->assertAccepted()->assertJsonPath('data.title', 'Comité semanal');
        $this->assertDatabaseCount('meetings', 1);

        $payload['audio'] = UploadedFile::fake()->create('reunion.m4a', 128, 'audio/mp4');
        $this->withToken($token)->withHeader('Idempotency-Key', 'iphone-test-0001')->post('/api/v1/meetings', $payload)->assertAccepted();
        $this->assertDatabaseCount('meetings', 1);
    }

    public function test_consent_and_idempotency_key_are_required(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone', ['meetings:upload'])->plainTextToken;
        $this->withToken($token)->post('/api/v1/meetings', [
            'audio' => UploadedFile::fake()->create('reunion.m4a', 128, 'audio/mp4'),
            'title' => 'Reunión', 'held_at' => now()->toIso8601String(), 'meeting_type' => 'Comité',
            'artifacts' => ['transcript'],
        ])->assertUnprocessable()->assertJsonValidationErrors('recording_consent_confirmed');
    }

    public function test_user_cannot_view_another_users_meeting(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $meeting = Meeting::create([
            'user_id' => $owner->id, 'title' => 'Privada', 'held_at' => now(), 'meeting_type' => 'Comité',
            'participants' => [], 'recording_consent_confirmed' => true, 'consent_confirmed_at' => now(),
            'artifacts' => ['transcript'], 'drive_connection_ids' => [], 'idempotency_key' => 'private-0001',
        ]);
        $token = $other->createToken('iPhone', ['meetings:upload'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/meetings/'.$meeting->id)->assertForbidden();
    }
}
