<?php

namespace Database\Factories;

use App\Models\TabularDataSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TabularDataSource> */
class TabularDataSourceFactory extends Factory
{
    protected $model = TabularDataSource::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return ['name' => Str::title($name), 'slug' => Str::slug($name), 'created_by' => User::factory()];
    }
}
