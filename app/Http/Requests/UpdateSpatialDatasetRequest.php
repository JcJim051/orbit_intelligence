<?php

namespace App\Http\Requests;

use App\Enums\DatasetFormVersionStatus;
use App\Models\SpatialDataset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSpatialDatasetRequest extends FormRequest
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
        /** @var SpatialDataset $spatialDataset */
        $spatialDataset = $this->route('spatialDataset');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('spatial_datasets', 'slug')->ignore($spatialDataset)],
            'sector' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'geometry_type' => ['required', 'in:point,line,polygon,none'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $dataset = $this->route('spatialDataset');
                if (! $dataset instanceof SpatialDataset) {
                    return;
                }

                $structureLocked = $dataset->physical_table !== null
                    || $dataset->versions()->whereIn('status', [
                        DatasetFormVersionStatus::Published->value,
                        DatasetFormVersionStatus::Retired->value,
                    ])->exists();

                if (! $structureLocked) {
                    return;
                }
                if ($this->string('slug')->toString() !== $dataset->slug) {
                    $validator->errors()->add('slug', 'El identificador no puede cambiar después de publicar el primer formulario.');
                }
                if ($this->string('geometry_type')->toString() !== $dataset->geometry_type) {
                    $validator->errors()->add('geometry_type', 'La geometría no puede cambiar después de publicar el primer formulario. Cree otro conjunto de datos.');
                }
            },
        ];
    }
}
