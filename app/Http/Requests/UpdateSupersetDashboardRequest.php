<?php

namespace App\Http\Requests;

use App\Models\Dashboard;
use App\Services\Analitica\DiccionarioAnalitica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSupersetDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dashboard = $this->route('dashboard');

        return $dashboard instanceof Dashboard
            && $dashboard->usesSuperset()
            && $this->user() !== null
            && $dashboard->canEdit($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'charts' => array_values(array_filter(
                (array) $this->input('charts', []),
                fn (mixed $chart): bool => is_array($chart) && filled($chart['title'] ?? null),
            )),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'dataset_name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
            'charts' => ['required', 'array', 'min:1', 'max:12'],
            'charts.*.key' => ['nullable', 'uuid'],
            'charts.*.title' => ['required', 'string', 'max:120'],
            'charts.*.type' => ['required', Rule::in(['big_number', 'bar', 'line', 'pie', 'table'])],
            'charts.*.dimension' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
            'charts.*.metric' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
            'charts.*.aggregation' => ['required', Rule::in(['SUM', 'AVG', 'MIN', 'MAX', 'COUNT', 'COUNT_DISTINCT'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $dataset = DiccionarioAnalitica::vista((string) $this->input('dataset_name'));

            if ($dataset === null) {
                $validator->errors()->add('dataset_name', 'Seleccione un dataset disponible en el catálogo analítico del SIID.');

                return;
            }

            $columns = collect($dataset['columnas'] ?? [])->pluck('nombre');

            foreach ((array) $this->input('charts', []) as $index => $chart) {
                if (! is_array($chart)) {
                    continue;
                }

                foreach (['metric' => 'métrica', 'dimension' => 'dimensión'] as $field => $label) {
                    $value = $chart[$field] ?? null;
                    if (filled($value) && ! $columns->contains($value)) {
                        $validator->errors()->add("charts.{$index}.{$field}", "La {$label} seleccionada no pertenece al dataset.");
                    }
                }
            }
        }];
    }
}
