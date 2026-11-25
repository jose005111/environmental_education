<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['sometimes', 'required', 'integer', Rule::exists('roles', 'id')],
            'zone_id' => ['sometimes', 'nullable', 'integer', Rule::exists('zones', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
