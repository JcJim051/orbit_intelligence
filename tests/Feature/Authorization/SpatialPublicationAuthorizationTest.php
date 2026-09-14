<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpatialPublicationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roleMatrix')]
    public function test_only_management_roles_can_approve_spatial_publication(UserRole $role, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->assertSame($allowed, Gate::forUser($user)->allows('approve-spatial-publication'));
    }

    /** @return array<string, array{UserRole, bool}> */
    public static function roleMatrix(): array
    {
        return [
            'usuario' => [UserRole::Member, false],
            'revisor' => [UserRole::Reviewer, false],
            'administrador técnico' => [UserRole::Admin, true],
            'gerente' => [UserRole::Manager, true],
            'apoyo administrativo' => [UserRole::ManagementSupport, true],
        ];
    }
}
