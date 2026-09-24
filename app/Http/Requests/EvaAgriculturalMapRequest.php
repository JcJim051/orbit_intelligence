<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaAgriculturalMapRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => [
                'sometimes',
                'integer',
                'between:'.config('eva.minimum_year').','.config('eva.maximum_year'),
            ],
            'crop' => ['sometimes', 'string', Rule::in(config('eva.crops'))],
            'metric' => ['sometimes', 'string', Rule::in(array_keys(config('eva.metrics')))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.between' => 'El año debe estar entre :min y :max.',
            'crop.in' => 'Seleccione un cultivo disponible en EVA para el Meta.',
            'metric.in' => 'Seleccione una métrica disponible: área sembrada, producción o rendimiento.',
        ];
    }
}
