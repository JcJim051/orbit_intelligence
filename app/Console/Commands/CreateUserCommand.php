<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateUserCommand extends Command
{
    protected $signature = 'users:create {email?} {--name=} {--role=member}';

    protected $description = 'Crea un usuario del equipo sin habilitar registro público';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Correo');
        $name = $this->option('name') ?: $this->ask('Nombre');
        $role = UserRole::tryFrom((string) $this->option('role'));
        if (! $role) {
            $this->error('Rol inválido: member, reviewer o admin.');

            return self::FAILURE;
        }
        $password = $this->secret('Contraseña inicial');
        if (! $password || strlen($password) < 12) {
            $this->error('Usa al menos 12 caracteres.');

            return self::FAILURE;
        }
        User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make($password), 'role' => $role, 'active' => true]);
        $this->info('Usuario creado o actualizado.');

        return self::SUCCESS;
    }
}
