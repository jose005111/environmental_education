<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'sort' => ['sometimes', 'string', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter' => ['sometimes', 'array'],
            'filter.*' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'per_page.integer' => 'O campo por página deve ser um número inteiro.',
            'per_page.min' => 'O mínimo de registos por página é 1.',
            'per_page.max' => 'O máximo de registos por página é 50.',
            'sort.string' => 'A ordenação deve ser um texto.',
            'search.string' => 'A pesquisa deve ser um texto.',
            'filter.array' => 'Os filtros devem ser um objeto.',
        ];
    }
}
