<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpenDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageOpenDataSources() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'popup_fields' => array_values(array_filter((array) $this->input('popup_fields'))),
            'filters' => array_values(array_filter((array) $this->input('filters'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url:https', 'max:1000'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:open_data_sources,slug', 'unique:geo_layers,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'attribution' => ['required', 'string', 'max:500'],
            'label_field' => ['nullable', 'string', 'max:120'],
            'metric_field' => ['nullable', 'string', 'max:120'],
            'aggregation' => ['required', Rule::in(['sum', 'avg', 'count', 'min', 'max'])],
            'popup_fields' => ['array', 'max:12'],
            'popup_fields.*' => ['string', 'max:120', 'distinct'],
            'filters' => ['array', 'max:4'],
            'filters.*' => ['string', 'max:120', 'distinct'],
            'palette' => ['required', Rule::in(['green', 'blue', 'orange', 'purple', 'red'])],
            'no_data_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'opacity' => ['required', 'numeric', 'between:0.1,1'],
            'target' => ['required', Rule::in(['new', 'existing'])],
            'viewer_id' => ['nullable', 'required_if:target,existing', 'ulid', 'exists:geo_viewers,id'],
            'viewer_name' => ['nullable', 'required_if:target,new', 'string', 'max:120'],
            'viewer_slug' => ['nullable', 'required_if:target,new', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:geo_viewers,slug'],
        ];
    }
}
