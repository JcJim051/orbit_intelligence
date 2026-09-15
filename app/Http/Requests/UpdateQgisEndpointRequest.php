<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQgisEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'qgis_host' => ['required', 'string', 'max:253', 'regex:/^[a-zA-Z0-9.-]+$/'],
            'qgis_port' => ['required', 'integer', 'between:1,65535'],
        ];
    }
}
