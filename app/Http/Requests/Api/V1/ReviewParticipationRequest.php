<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ParticipationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewParticipationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        $merge = [];

        if (! $this->filled('status') && $this->filled('decision')) {
            $merge['status'] = $this->input('decision');
        }

        if (! $this->filled('status') && ($this->filled('approved') || $this->filled('is_approved'))) {
            $approved = $this->filled('approved') ? $this->boolean('approved') : $this->boolean('is_approved');

            $merge['status'] = $approved
                ? ParticipationStatus::VALIDADO->value
                : ParticipationStatus::REJEITADO->value;
        }

        if (! $this->filled('review_note') && $this->filled('note')) {
            $merge['review_note'] = $this->input('note');
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([ParticipationStatus::VALIDADO->value, ParticipationStatus::REJEITADO->value]),
            ],
            'review_note' => ['sometimes', 'nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O resultado da revisão é obrigatório.',
            'status.in' => 'O resultado deve ser validado ou rejeitado.',
            'review_note.max' => 'A nota da revisão não pode exceder 300 caracteres.',
        ];
    }
}
