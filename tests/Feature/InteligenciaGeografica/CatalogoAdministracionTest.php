<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\UserRole;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\Suelos\TipoEstudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoAdministracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_change_or_exchange_catalogs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->assertTrue($admin->can('create', TipoEstudio::class));
        $this->assertTrue($admin->can('update', new TipoEstudio));
        $this->assertTrue($admin->can('export', Capa::class));
        $this->assertTrue($admin->can('import', Capa::class));
        $this->assertFalse($member->can('create', TipoEstudio::class));
        $this->assertFalse($member->can('import', Capa::class));
        $this->assertFalse($member->can('export', TipoEstudio::class));
    }
}
