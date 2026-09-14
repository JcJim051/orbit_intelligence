<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->active;
    }

    protected function prepareForValidation(): void
    {
        foreach (['participants', 'artifacts', 'drive_connection_ids'] as $field) {
            if (is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                if (is_array($decoded)) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
        if (is_array($this->input('participants'))) {
            $this->merge(['participants' => collect($this->input('participants'))->filter(fn ($participant) => filled($participant['name'] ?? null))->values()->all()]);
        }
    }

    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'max:'.config('meetings.max_size_kb'), 'mimes:mp3,mp4,mpeg,mpga,m4a,wav,webm'],
            'title' => ['required', 'string', 'max:180'],
            'held_at' => ['required', 'date'],
            'meeting_type' => ['required', 'string', 'max:100'],
            'participants' => ['nullable', 'array', 'max:100'],
            'participants.*.name' => ['required', 'string', 'max:120'],
            'participants.*.email' => ['nullable', 'email', 'max:180'],
            'recording_consent_confirmed' => ['accepted'],
            'artifacts' => ['required', 'array', 'min:1'],
            'artifacts.*' => ['required', Rule::in(['audio', 'transcript', 'minutes_markdown', 'minutes_pdf'])],
            'drive_connection_ids' => ['nullable', 'array'],
            'drive_connection_ids.*' => ['string', Rule::exists('drive_connections', 'id')->where('active', true)],
            'reviewer_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', [UserRole::Reviewer->value, UserRole::Admin->value])->where('active', true)),
            ],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $audio = $this->file('audio');

        Log::warning('Carga de reunión rechazada por validación.', [
            'user_id' => $this->user()?->id,
            'errors' => $validator->errors()->toArray(),
            'has_audio' => $this->hasFile('audio'),
            'audio' => $audio ? [
                'name' => $audio->getClientOriginalName(),
                'mime' => $audio->getClientMimeType(),
                'size' => $audio->getSize(),
                'upload_error' => $audio->getError(),
            ] : null,
            'input_keys' => array_keys($this->except('audio')),
            'held_at' => $this->input('held_at'),
            'artifacts' => $this->input('artifacts'),
        ]);

        parent::failedValidation($validator);
    }
}
