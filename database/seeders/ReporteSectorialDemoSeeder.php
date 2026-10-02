<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Solo para probar en local: crea un usuario de Gerencia y un usuario por cada una de las dos primeras
 * dependencias, todos con la contraseña "password". No se llama desde DatabaseSeeder.
 *
 * php artisan db:seed --class=ReporteSectorialDemoSeeder
 */
class ReporteSectorialDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('El seeder de demostración no se ejecuta en producción.');
        }

        User::query()->updateOrCreate(['email' => 'gerencia.demo@siid.local'], ['name' => 'Gerencia (demo)', 'password' => 'password', 'role' => UserRole::Manager, 'active' => true]);

        foreach (Dependencia::query()->orderBy('id')->limit(2)->get() as $indice => $dependencia) {
            $usuario = User::query()->updateOrCreate(
                ['email' => 'sector'.($indice + 1).'.demo@siid.local'],
                ['name' => 'Enlace '.$dependencia->nombre.' (demo)', 'password' => 'password', 'role' => UserRole::Member, 'active' => true],
            );
            $usuario->dependencias()->syncWithoutDetaching([$dependencia->id]);
        }
    }
}
