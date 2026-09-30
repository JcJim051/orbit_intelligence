<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_updates_password_and_deactivates_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Gestora SIG',
            'email' => 'gestora@example.com',
            'password' => 'clave-segura-123',
            'password_confirmation' => 'clave-segura-123',
            'role' => UserRole::SiidManager->value,
            'active' => '1',
        ])->assertRedirect();

        $user = User::query()->where('email', 'gestora@example.com')->firstOrFail();
        $this->assertSame(UserRole::SiidManager, $user->role);
        $this->assertTrue($user->active);

        $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => 'Gestora de Indicadores',
            'email' => 'indicadores@example.com',
            'role' => UserRole::Manager->value,
            'active' => '1',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Gestora de Indicadores', $user->name);
        $this->assertSame('indicadores@example.com', $user->email);
        $this->assertSame(UserRole::Manager, $user->role);

        $this->actingAs($admin)->patch(route('admin.users.password.update', $user), [
            'password' => 'otra-clave-segura-123',
            'password_confirmation' => 'otra-clave-segura-123',
        ])->assertRedirect();

        $user->refresh();
        $this->assertTrue(Hash::check('otra-clave-segura-123', $user->password));

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect();
        $this->assertFalse($user->fresh()->active);
    }

    public function test_user_administration_opens_the_filament_data_table(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Administradora SIID']);

        $this->actingAs($admin)
            ->get('/gestion/usuarios')
            ->assertOk()
            ->assertSee('Administradora SIID')
            ->assertSee('Crear usuario');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertRedirect(\App\Filament\Resources\UserResource::getUrl());
    }

    public function test_manager_only_grants_or_removes_siid_manager_role(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $member = User::factory()->create(['role' => UserRole::Member, 'active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'active' => true]);

        $this->actingAs($manager)->patch(route('admin.users.update', $member), [
            'name' => 'Nombre ignorado',
            'email' => 'ignorado@example.com',
            'role' => UserRole::SiidManager->value,
            'active' => '1',
        ])->assertRedirect();

        $member->refresh();
        $this->assertSame(UserRole::SiidManager, $member->role);
        $this->assertNotSame('Nombre ignorado', $member->name);

        $this->actingAs($manager)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Member->value,
            'active' => '1',
        ])->assertForbidden();
    }

    public function test_user_cannot_deactivate_or_downgrade_itself(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'active' => true]);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Admin->value,
            'active' => '0',
        ])->assertStatus(422);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => UserRole::Manager->value,
            'active' => '1',
        ])->assertStatus(422);
    }
}
