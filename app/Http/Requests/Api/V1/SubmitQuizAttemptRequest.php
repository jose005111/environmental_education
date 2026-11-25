<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuizAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $answers = $this->input('answers') ?? $this->input('responses');

        if ($answers === null) {
            $input = $this->input();

            if (array_is_list($input)) {
                $answers = $input;
            } elseif (array_key_exists('question_id', $input) || array_key_exists('option_id', $input)) {
                $answers = [$input];
            }
        }

        if (is_array($answers)) {
            if (! array_is_list($answers) && array_key_exists('question_id', $answers)) {
                $answers = [$answers];
            }

            $this->merge(['answers' => $answers]);
        }
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'exists:quiz_questions,id'],
            'answers.*.option_id' => ['required', 'integer', 'exists:quiz_options,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'answers.required' => 'As respostas são obrigatórias.',
            'answers.min' => 'As respostas são obrigatórias.',
            'answers.*.question_id.required' => 'Indique a pergunta respondida.',
            'answers.*.question_id.exists' => 'A pergunta indicada não existe.',
            'answers.*.option_id.required' => 'Indique a opção escolhida.',
            'answers.*.option_id.exists' => 'A opção indicada não existe.',
        ];
    }
}
