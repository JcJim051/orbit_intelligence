<?php

namespace App\Http\Requests;

use App\Models\Dashboard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDashboardRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('config'))) {
            $this->merge(['config' => json_decode($this->input('config'), true)]);
        }
    }

    public function authorize(): bool
    {
        $dashboard = $this->route('dashboard');

        return $dashboard instanceof Dashboard && $this->user() !== null && $dashboard->canEdit($this->user());
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'config' => ['required', 'array'],
            'config.widgets' => ['required', 'array', 'max:60'],
            'config.widgets.*.id' => ['required', 'string', 'max:80', 'distinct'],
            'config.widgets.*.type' => ['required', Rule::in(['map', 'indicator', 'bar', 'line', 'donut', 'pyramid', 'table', 'text', 'filter'])],
            'config.widgets.*.title' => ['required', 'string', 'max:120'],
            'config.widgets.*.scope' => ['required', Rule::in(['departamental_fijo', 'seleccion_territorial', 'global'])],
            'config.widgets.*.x' => ['required', 'integer', 'between:0,11'],
            'config.widgets.*.y' => ['required', 'integer', 'between:0,100'],
            'config.widgets.*.w' => ['required', 'integer', 'between:1,12'],
            'config.widgets.*.h' => ['required', 'integer', 'between:1,12'],
            'config.widgets.*.query' => ['nullable', 'array'],
            'config.data_source_id' => ['nullable', 'ulid', 'exists:tabular_data_sources,id'],
            'config.map.geo_viewer_id' => ['nullable', 'ulid', 'exists:geo_viewers,id'],
            'config.map.join_layer_field' => ['nullable', 'string', 'max:120'],
            'config.map.join_data_field' => ['nullable', 'string', 'max:120'],
            'config.theme' => ['nullable', 'array'],
            'config.global_filters' => ['nullable', 'array'],
        ];
    }
}
