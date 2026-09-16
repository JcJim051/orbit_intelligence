<?php

namespace App\Http\Requests;

use App\Enums\GeoLayerAccessPolicy;
use App\Enums\GeoViewerStatus;
use App\Models\GeoLayer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeoLayerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        /** @var GeoLayer|null $geoLayer */
        $geoLayer = $this->route('geoLayer');
        $this->merge([
            'access_policy' => $this->input('access_policy', $geoLayer?->access_policy?->value ?? GeoLayerAccessPolicy::Pending->value),
            'download_format' => $this->input('download_format') ?: ($this->input('source_type') === 'geojson' ? 'geojson' : null),
        ]);
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
        /** @var GeoLayer $geoLayer */
        $geoLayer = $this->route('geoLayer');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('geo_layers', 'slug')->ignore($geoLayer)],
            'group_name' => ['nullable', 'string', 'max:120'],
            'source_type' => ['required', 'in:geojson,wms'],
            'source_url' => ['required', 'string', 'max:2000', 'starts_with:http://,https://,/data/geovisores/,/api/public/'],
            'source_layer_name' => ['nullable', 'required_if:source_type,wms', 'string', 'max:255'],
            'geometry_type' => ['required', 'in:point,line,polygon,mixed'],
            'popup_fields' => ['nullable', 'string', 'max:4000'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'fill_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'weight' => ['required', 'integer', 'between:0,10'],
            'radius' => ['required', 'integer', 'between:1,30'],
            'attribution' => ['nullable', 'string', 'max:500'],
            'min_zoom' => ['required', 'integer', 'between:0,22'],
            'max_zoom' => ['required', 'integer', 'between:0,22', 'gte:min_zoom'],
            'active' => ['nullable', 'boolean'],
            'access_policy' => ['required', Rule::enum(GeoLayerAccessPolicy::class)],
            'download_url' => ['nullable', 'string', 'max:2000', 'starts_with:http://,https://,/data/geovisores/,/api/public/'],
            'download_format' => ['nullable', 'in:geojson,csv,kml,gpkg,zip'],
            'restriction_reason' => ['nullable', 'required_if:access_policy,view_only', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $policy = $this->string('access_policy')->toString();
            if ($policy === GeoLayerAccessPolicy::ViewOnly->value && $this->string('source_type')->toString() !== 'wms') {
                $validator->errors()->add('access_policy', 'Una capa de solo visualización debe publicarse mediante WMS; GeoJSON entregaría los datos completos al navegador.');
            }
            if ($policy === GeoLayerAccessPolicy::Downloadable->value
                && $this->string('source_type')->toString() === 'wms'
                && ! $this->filled('download_url')) {
                $validator->errors()->add('download_url', 'Indique el archivo público que podrá descargarse para esta capa WMS.');
            }
            if ($policy === GeoLayerAccessPolicy::Downloadable->value
                && $this->string('source_type')->toString() === 'geojson'
                && ! $this->filled('download_url')
                && $this->string('download_format')->toString() !== 'geojson') {
                $validator->errors()->add('download_url', 'Indique la URL del archivo correspondiente al formato de descarga seleccionado.');
            }

            /** @var GeoLayer|null $geoLayer */
            $geoLayer = $this->route('geoLayer');
            if ($policy === GeoLayerAccessPolicy::Pending->value
                && $geoLayer?->viewers()->where('status', GeoViewerStatus::Published->value)->exists()) {
                $validator->errors()->add('access_policy', 'Una capa que ya aparece en un geovisor público no puede quedar pendiente de clasificación.');
            }
        }];
    }
}
