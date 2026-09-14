<?php

namespace Tests\Unit;

use App\Models\DriveConnection;
use App\Services\GoogleDrive\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_id_prevents_duplicate_remote_uploads(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('export.md', 'contenido');
        $connection = DriveConnection::create([
            'label' => 'Personal', 'active' => true, 'access_token' => 'access-token',
            'refresh_token' => 'refresh-token', 'token_expires_at' => now()->addHour(),
        ]);
        Http::fake([
            'https://www.googleapis.com/drive/v3/files*' => Http::response(['files' => [[
                'id' => 'existing-id', 'name' => 'acta-v001.md', 'size' => 9, 'md5Checksum' => 'abc',
            ]]]),
        ]);

        $result = app(GoogleDriveService::class)->upload(
            $connection, Storage::disk('local')->path('export.md'), 'acta-v001.md', 'text/markdown', 'parent-id', 'export-ulid',
        );

        $this->assertSame('existing-id', $result['id']);
        Http::assertSentCount(1);
    }
}
