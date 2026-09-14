<?php

namespace App\Http\Requests;

use App\Enums\DatasetFieldType;
use App\Enums\HistoricalDataPolicy;
use App\Models\DatasetFormVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDatasetFormFieldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $version = $this->route('version');

        return ($this->user()?->isAdmin() ?? false)
            && $version instanceof DatasetFormVersion
            && $version->isDraft();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => [
                'required', 'string', 'max:63', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn(['id', 'geometry', 'geom', 'created_at', 'updated_at', 'created_by', 'created_by_email', 'status', 'record_status', 'source', 'form_version', 'form_version_id']),
                Rule::unique('dataset_form_fields', 'key')->where('dataset_form_version_id', $this->route('version')?->id),
            ],
            'label' => ['required', 'string', 'max:120'],
            'section' => ['required', 'string', 'max:120'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'field_type' => ['required', Rule::enum(DatasetFieldType::class)],
            'unit' => ['nullable', 'string', 'max:50'],
            'required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'required_if:field_type,select,multi_select', 'string', 'max:5000'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric', 'gte:min_value'],
            'max_length' => ['nullable', 'integer', 'between:1,10000'],
            'historical_policy' => ['required', Rule::enum(HistoricalDataPolicy::class)],
            'visible_in_qgis' => ['nullable', 'boolean'],
            'public_visible' => ['nullable', 'boolean'],
            'available_for_analytics' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,1000'],
        ];
    }
}
