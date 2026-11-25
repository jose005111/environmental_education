<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\QuizStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(QuizStatus::class)],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.statement' => ['required', 'string'],
            'questions.*.explanation' => ['sometimes', 'nullable', 'string'],
            'questions.*.position' => ['sometimes', 'integer', 'min:0'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.options.*.text' => ['required', 'string', 'max:300'],
            'questions.*.options.*.is_correct' => ['required', 'boolean'],
            'questions.*.options.*.position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'A categoria do questionário é obrigatória.',
            'category_id.integer' => 'A categoria do questionário é inválida.',
            'category_id.exists' => 'A categoria indicada não existe.',
            'title.required' => 'O título do questionário é obrigatório.',
            'title.max' => 'O título do questionário é demasiado longo.',
            'questions.required' => 'O questionário deve ter perguntas.',
            'questions.min' => 'O questionário deve ter pelo menos uma pergunta.',
            'questions.*.options.min' => 'Cada pergunta deve ter entre 2 e 6 opções.',
            'questions.*.options.max' => 'Cada pergunta deve ter entre 2 e 6 opções.',
            'questions.*.options.*.is_correct.required' => 'Indique qual é a opção correcta.',
        ];
    }
}
