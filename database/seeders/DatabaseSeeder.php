<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InvestmentEntitySeeder::class,
            DependenciaSeeder::class,
            MunicipioSeeder::class,
            EstructuraPlanDesarrolloSeeder::class,
            MetaResultadoSeeder::class,
        ]);

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $this->call([
                InteligenciaGeograficaSeeder::class,
                SuelosCatalogoSeeder::class,
            ]);
        }

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password']
        );
    }
}
