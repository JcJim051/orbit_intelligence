<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Postgis\BootstrapLocalPostgis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class LocalPostgisBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_administrator_cannot_start_local_postgis(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->post(route('admin.postgis.local.store'))->assertForbidden();
    }

    public function test_administrator_can_start_local_postgis_and_receives_encrypted_one_time_qgis_credentials(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $credentials = [
            'host' => '127.0.0.1',
            'port' => 55432,
            'database' => 'siid_meta',
            'username' => 'qgis_editor',
            'password' => 'generated-qgis-password',
            'sslmode' => 'prefer',
        ];
        $bootstrap = $this->mock(BootstrapLocalPostgis::class);
        $bootstrap->expects('handle')->once()->andReturn($credentials);

        $response = $this->actingAs($admin)->post(route('admin.postgis.local.store'));

        $response->assertRedirect()->assertSessionHas('status');
        $encrypted = session('local_qgis_credentials');
        $this->assertIsString($encrypted);
        $this->assertStringNotContainsString($credentials['password'], $encrypted);
        $this->assertSame($credentials, json_decode(Crypt::decryptString($encrypted), true));
        $this->assertDatabaseHas('audit_logs', ['event' => 'local_postgis_bootstrapped']);
        $this->assertArrayNotHasKey(
            'password',
            AuditLog::query()->where('event', 'local_postgis_bootstrapped')->firstOrFail()->metadata,
        );
    }
}
