<?php

namespace Tests\Feature\Admin;

use App\Enums\SpatialImportStatus;
use App\Enums\UserRole;
use App\Models\SpatialImport;
use App\Models\User;
use App\Services\Postgis\ProvisionSpatialImportStaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SpatialImportAccessControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_renews_expired_qgis_access_for_an_approved_import(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Approved,
            'expires_at' => now()->subDay(),
        ]);
        $this->mock(ProvisionSpatialImportStaging::class, function (MockInterface $mock) use ($import): void {
            $mock->shouldReceive('renewAccess')
                ->once()
                ->withArgs(fn (SpatialImport $bound, $expiresAt): bool => $bound->is($import) && $expiresAt->greaterThan(now()->addHours(70)))
                ->andReturn(1);
        });

        $this->actingAs($admin)
            ->post(route('admin.spatial-imports.access.store', $import), ['valid_for_hours' => 72])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $message): bool => str_contains($message, 'Acceso QGIS renovado') && str_contains($message, '1 capa(s)'));

        $this->assertTrue($import->fresh()->expires_at->greaterThan(now()->addHours(70)));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'spatial_import_access_renewed',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_member_cannot_renew_qgis_access(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $import = SpatialImport::factory()->create(['expires_at' => now()->subDay()]);

        $this->actingAs($member)
            ->post(route('admin.spatial-imports.access.store', $import), ['valid_for_hours' => 72])
            ->assertForbidden();
    }

    public function test_closed_import_cannot_be_renewed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Closed,
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.spatial-imports.access.store', $import), ['valid_for_hours' => 72])
            ->assertRedirect()
            ->assertSessionHas('error', 'Esta importación no admite renovación.');

        $this->assertTrue($import->fresh()->expires_at->isPast());
    }
}
