<?php

namespace Database\Factories;

use App\Models\InvestmentEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentEntity>
 */
class InvestmentEntityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->company(),
            'acronym' => strtoupper(fake()->lexify('???')),
            'aliases' => [fake()->company()],
            'source_url' => 'https://meta.gov.co/mapa-del-sitio',
            'active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
