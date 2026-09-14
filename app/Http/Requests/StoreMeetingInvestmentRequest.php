<?php

namespace App\Http\Requests;

use App\Models\Meeting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingInvestmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $meeting = Meeting::find($this->string('meeting_id')->toString());

        return $meeting !== null && ($this->user()?->can('update', $meeting) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'meeting_id' => ['required', 'string', 'exists:meetings,id'],
            'agenda_reason' => ['required', 'string', 'max:3000'],
            'prepared_questions' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
