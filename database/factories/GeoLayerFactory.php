<?php

namespace Database\Factories;

use App\Enums\GeoLayerAccessPolicy;
use App\Models\GeoLayer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GeoLayer>
 */
class GeoLayerFactory extends Factory
{
    protected $model = GeoLayer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'group_name' => 'Territorio',
            'source_type' => 'geojson',
            'source_url' => 'https://example.com/'.Str::slug($name).'.geojson',
            'geometry_type' => 'mixed',
            'popup_fields' => ['nombre'],
            'style' => [
                'color' => '#4338ca',
                'fillColor' => '#818cf8',
                'weight' => 2,
                'radius' => 7,
            ],
            'attribution' => 'Fuente de prueba',
            'min_zoom' => 0,
            'max_zoom' => 18,
            'active' => true,
            'access_policy' => GeoLayerAccessPolicy::Downloadable,
            'download_format' => 'geojson',
        ];
    }
}
