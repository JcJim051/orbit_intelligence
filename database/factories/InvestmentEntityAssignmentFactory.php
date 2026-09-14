<?php

namespace Database\Factories;

use App\Models\InvestmentEntity;
use App\Models\InvestmentEntityAssignment;
use App\Models\InvestmentProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentEntityAssignment>
 */
class InvestmentEntityAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'investment_project_id' => InvestmentProject::factory(),
            'investment_entity_id' => InvestmentEntity::factory(),
            'role' => 'primary',
            'status' => 'confirmed',
            'method' => 'manual',
            'confidence' => 1,
            'evidence' => ['field' => 'manual'],
        ];
    }
}
