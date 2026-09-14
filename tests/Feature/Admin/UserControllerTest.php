<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('managementRoles')]
    public function test_admin_assigns_management_publication_roles_from_frontend(UserRole $role): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Responsable de publicación',
            'email' => $role->value.'@meta.gov.co',
            'password' => 'contrasena-segura-2026',
            'role' => $role->value,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'email' => $role->value.'@meta.gov.co',
            'role' => $role->value,
            'active' => true,
        ]);
    }

    /** @return array<string, array{UserRole}> */
    public static function managementRoles(): array
    {
        return [
            'gerente' => [UserRole::Manager],
            'apoyo administrativo' => [UserRole::ManagementSupport],
        ];
    }

    public function test_administrator_cannot_remove_own_administrator_role(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'role' => UserRole::Manager->value,
            'active' => true,
        ])->assertStatus(422);

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }
}
