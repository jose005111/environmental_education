<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssignReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('assign', $this->route('report'));

        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_to.exists' => 'O responsável indicado não existe.',
        ];
    }
}
