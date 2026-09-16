<?php

namespace App\Http\Requests;

use App\Services\Postgis\SpatialReferenceSystems;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpatialDatasetRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['storage_srid' => $this->input('storage_srid', 4326)]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:spatial_datasets,slug'],
            'sector' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'geometry_type' => ['required', 'in:point,line,polygon,none'],
            'storage_srid' => ['required', 'integer', Rule::in(array_keys(SpatialReferenceSystems::storageOptions()))],
        ];
    }
}
