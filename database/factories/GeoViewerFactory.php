<?php

namespace Database\Factories;

use App\Enums\GeoViewerStatus;
use App\Models\GeoViewer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GeoViewer>
 */
class GeoViewerFactory extends Factory
{
    protected $model = GeoViewer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'center_latitude' => 4.15,
            'center_longitude' => -73.63,
            'initial_zoom' => 8,
            'status' => GeoViewerStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => GeoViewerStatus::Published,
            'published_at' => now(),
        ]);
    }
}
