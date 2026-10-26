<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TrazadoresPresupuestalesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TrazadorSeeder::class,
            TrazadorCategoriaSeeder::class,
            PoliticaPublicaDepartamentalSeeder::class,
        ]);
    }
}
