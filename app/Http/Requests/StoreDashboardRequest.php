<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageDashboards() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:dashboards,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'template' => ['required', Rule::in(['blank', 'population'])],
        ];
    }
}
