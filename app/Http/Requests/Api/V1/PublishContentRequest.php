<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ContentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ContentStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'O estado é obrigatório.',
        ];
    }
}
