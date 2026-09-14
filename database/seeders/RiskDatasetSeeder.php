<?php

namespace Database\Seeders;

use App\Enums\DatasetFieldType;
use App\Enums\DatasetFormVersionStatus;
use App\Enums\DatasetStatus;
use App\Enums\HistoricalDataPolicy;
use App\Models\DatasetFormField;
use App\Models\SpatialDataset;
use App\Models\User;
use Illuminate\Database\Seeder;

class RiskDatasetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $administrator = User::query()->where('role', 'admin')->first();
        $dataset = SpatialDataset::updateOrCreate(['slug' => 'puntos-criticos'], [
            'name' => 'Puntos críticos',
            'sector' => 'Gestión del Riesgo',
            'description' => 'Registro institucional y versionado de puntos críticos del departamento del Meta.',
            'geometry_type' => 'point',
            'status' => DatasetStatus::Draft,
            'created_by' => $administrator?->id,
        ]);
        $version = $dataset->versions()->firstOrCreate(['version' => 1], [
            'status' => DatasetFormVersionStatus::Draft,
            'created_by' => $administrator?->id,
        ]);

        if (! $version->isDraft()) {
            return;
        }

        foreach ($this->fields() as $field) {
            DatasetFormField::updateOrCreate([
                'dataset_form_version_id' => $version->id,
                'key' => $field['key'],
            ], $field);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fields(): array
    {
        return [
            $this->field('tipo_evento', 'Tipo de evento', DatasetFieldType::Select, 10, [
                'Inundación', 'Movimiento en masa', 'Incendio forestal', 'Erosión de ribera', 'Vendaval', 'Otro',
            ], required: true, public: true),
            $this->field('nivel_riesgo', 'Nivel de riesgo', DatasetFieldType::Select, 20, [
                'Bajo', 'Medio', 'Alto', 'Crítico',
            ], required: true, public: true),
            $this->field('fecha_reporte', 'Fecha del reporte', DatasetFieldType::Date, 30, required: true, public: true),
            $this->field('estado_atencion', 'Estado de atención', DatasetFieldType::Select, 40, [
                'Reportado', 'En validación', 'Validado', 'En intervención', 'Cerrado',
            ], required: true, public: true),
            $this->field('descripcion', 'Descripción', DatasetFieldType::LongText, 50, validation: ['max_length' => 2000], public: true),
            $this->field('poblacion_afectada_inicial', 'Población afectada inicial', DatasetFieldType::Integer, 60, unit: 'personas', validation: ['min' => 0], public: true),
            $this->field('cultivos_afectados_inicial', 'Cultivos afectados inicialmente', DatasetFieldType::Decimal, 70, unit: 'hectáreas', validation: ['min' => 0]),
            $this->field('animales_afectados_inicial', 'Animales afectados inicialmente', DatasetFieldType::Integer, 80, unit: 'animales', validation: ['min' => 0]),
            $this->field('fuente_reporte', 'Fuente del reporte', DatasetFieldType::ShortText, 90, validation: ['max_length' => 250], required: true),
        ];
    }

    /**
     * @param  array<int, string>|null  $options
     * @param  array<string, int|float>  $validation
     * @return array<string, mixed>
     */
    private function field(
        string $key,
        string $label,
        DatasetFieldType $type,
        int $order,
        ?array $options = null,
        ?string $unit = null,
        array $validation = [],
        bool $required = false,
        bool $public = false,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'section' => str_contains($key, 'afectad') ? 'Afectación inicial' : 'Información general',
            'help_text' => null,
            'field_type' => $type,
            'unit' => $unit,
            'required' => $required,
            'options' => $options,
            'validation_rules' => $validation ?: null,
            'historical_policy' => HistoricalDataPolicy::FutureOnly,
            'introduced_in_version' => 1,
            'visible_in_qgis' => true,
            'public_visible' => $public,
            'available_for_analytics' => true,
            'sort_order' => $order,
        ];
    }
}
