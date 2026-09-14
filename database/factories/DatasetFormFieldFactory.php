<?php

namespace Database\Factories;

use App\Enums\DatasetFieldType;
use App\Enums\HistoricalDataPolicy;
use App\Models\DatasetFormField;
use App\Models\DatasetFormVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DatasetFormField>
 */
class DatasetFormFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'dataset_form_version_id' => DatasetFormVersion::factory(),
            'key' => Str::snake($label),
            'label' => Str::title($label),
            'section' => 'Información general',
            'help_text' => fake()->sentence(),
            'field_type' => DatasetFieldType::ShortText,
            'unit' => null,
            'required' => false,
            'options' => null,
            'validation_rules' => null,
            'historical_policy' => HistoricalDataPolicy::FutureOnly,
            'introduced_in_version' => 1,
            'visible_in_qgis' => true,
            'public_visible' => false,
            'available_for_analytics' => true,
            'sort_order' => 10,
        ];
    }
}
