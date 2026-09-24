<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeOpenDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageOpenDataSources() ?? false;
    }

    public function rules(): array
    {
        return ['url' => ['required', 'url:https', 'max:1000']];
    }
}
