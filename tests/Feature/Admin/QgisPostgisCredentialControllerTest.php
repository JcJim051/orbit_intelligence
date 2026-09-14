<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Postgis\ManagedPostgisConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class QgisPostgisCredentialControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_administrator_can_rotate_qgis_credentials(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->post(route('admin.postgis.qgis-credential.store'))->assertForbidden();
    }

    public function test_administrator_can_rotate_and_receive_an_encrypted_one_time_credential(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $credentials = [
            'host' => '127.0.0.1',
            'port' => 55432,
            'database' => 'siid_meta',
            'username' => 'qgis_editor',
            'password' => 'new-qgis-password',
            'sslmode' => 'prefer',
        ];
        $configuration = $this->mock(ManagedPostgisConfiguration::class);
        $configuration->expects('rotateQgisPassword')->once()->andReturn($credentials);

        $response = $this->actingAs($admin)->post(route('admin.postgis.qgis-credential.store'));

        $response->assertRedirect()->assertSessionHas('status');
        $encrypted = session('local_qgis_credentials');
        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString($credentials['password'], $encrypted);
        $this->assertSame($credentials, json_decode(Crypt::decryptString($encrypted), true));

        $audit = AuditLog::query()->where('event', 'qgis_postgis_credential_rotated')->firstOrFail();
        $this->assertArrayNotHasKey('password', $audit->metadata);
    }
}
