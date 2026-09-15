<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostgisConnectionRequest extends FormRequest
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
            'host' => ['required', 'string', 'max:253', 'regex:/^[a-zA-Z0-9.-]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'qgis_host' => ['required', 'string', 'max:253', 'regex:/^[a-zA-Z0-9.-]+$/'],
            'qgis_port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:63', 'regex:/^[a-z][a-z0-9_]*$/'],
            'sslmode' => ['required', Rule::in(['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'])],
            'admin_username' => ['required', 'string', 'max:63', 'regex:/^[a-z][a-z0-9_]*$/'],
            'admin_password' => ['required', 'string', 'min:16', 'max:255'],
            'app_username' => ['required', 'string', 'max:63', 'regex:/^[a-z][a-z0-9_]*$/', 'different:admin_username'],
            'app_password' => ['required', 'string', 'min:16', 'max:255'],
            'qgis_username' => ['required', 'string', 'max:63', 'regex:/^[a-z][a-z0-9_]*$/', 'different:admin_username,app_username'],
            'qgis_password' => ['required', 'string', 'min:16', 'max:255'],
            'reader_username' => ['required', 'string', 'max:63', 'regex:/^[a-z][a-z0-9_]*$/', 'different:admin_username,app_username,qgis_username'],
            'reader_password' => ['required', 'string', 'min:16', 'max:255'],
        ];
    }
}
