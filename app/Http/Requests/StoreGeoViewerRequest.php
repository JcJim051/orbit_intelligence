<?php

namespace App\Http\Requests;

use App\Enums\GeoViewerStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGeoViewerRequest extends FormRequest
{
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
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:geo_viewers,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'center_latitude' => ['required', 'numeric', 'between:-90,90'],
            'center_longitude' => ['required', 'numeric', 'between:-180,180'],
            'initial_zoom' => ['required', 'integer', 'between:0,22'],
            'status' => ['required', Rule::enum(GeoViewerStatus::class), Rule::notIn([GeoViewerStatus::Published->value])],
        ];
    }
}
