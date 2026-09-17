<?php

namespace App\Http\Requests;

use App\Enums\GeoViewerStatus;
use App\Models\GeoViewer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeoViewerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $geoViewer = $this->route('geoViewer');

        return ($this->user()?->isAdmin() ?? false)
            && $geoViewer instanceof GeoViewer;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var GeoViewer $geoViewer */
        $geoViewer = $this->route('geoViewer');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('geo_viewers', 'slug')->ignore($geoViewer), ...($geoViewer->isPublished() ? [Rule::in([$geoViewer->slug])] : [])],
            'description' => ['nullable', 'string', 'max:1000'],
            'center_latitude' => ['required', 'numeric', 'between:-90,90'],
            'center_longitude' => ['required', 'numeric', 'between:-180,180'],
            'initial_zoom' => ['required', 'integer', 'between:0,22'],
            'status' => ['required', Rule::enum(GeoViewerStatus::class), $geoViewer->isPublished()
                ? Rule::in([GeoViewerStatus::Published->value])
                : Rule::notIn([GeoViewerStatus::Published->value])],
            'layers' => ['nullable', 'array', 'max:100'],
            'layers.*.geo_layer_id' => ['required', 'ulid', 'distinct', 'exists:geo_layers,id'],
            'layers.*.included' => ['nullable', 'boolean'],
            'layers.*.label' => ['nullable', 'string', 'max:120'],
            'layers.*.group_name' => ['nullable', 'string', 'max:120'],
            'layers.*.sort_order' => ['required', 'integer', 'between:0,1000'],
            'layers.*.visible_by_default' => ['nullable', 'boolean'],
            'layers.*.show_in_legend' => ['nullable', 'boolean'],
            'layers.*.opacity' => ['required', 'numeric', 'between:0,1'],
        ];
    }
}
