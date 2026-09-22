<?php

namespace Database\Factories;

use App\Enums\DashboardStatus;
use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Dashboard> */
class DashboardFactory extends Factory
{
    protected $model = Dashboard::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'status' => DashboardStatus::Draft,
            'owner_id' => User::factory(),
            'draft_config' => ['data_source_id' => null, 'map' => [], 'global_filters' => [], 'widgets' => [[
                'id' => 'intro', 'type' => 'text', 'title' => 'Introducción', 'scope' => 'global',
                'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2, 'query' => [],
            ]]],
        ];
    }
}
