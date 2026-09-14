<?php

namespace Database\Factories;

use App\Enums\SpatialImportStatus;
use App\Models\SpatialImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpatialImport>
 */
class SpatialImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = mb_strtolower(Str::random(12));

        return [
            'name' => fake()->words(3, true),
            'sector' => 'Gestión del Riesgo',
            'purpose' => fake()->sentence(),
            'status' => SpatialImportStatus::StagingReady,
            'staging_schema' => 'staging_'.$token,
            'database_username' => 'stg_'.$token,
            'database_password' => Str::password(40, symbols: false),
            'expires_at' => now()->addDays(3),
            'created_by' => User::factory(),
        ];
    }
}
