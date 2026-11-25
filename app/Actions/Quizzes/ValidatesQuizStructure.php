<?php

namespace App\Actions\Quizzes;

use Illuminate\Validation\ValidationException;

trait ValidatesQuizStructure
{
    private function validateQuestions(array $questions): void
    {
        foreach ($questions as $index => $question) {
            $options = $question['options'] ?? [];

            if (count($options) < 2 || count($options) > 6) {
                throw ValidationException::withMessages([
                    "questions.$index.options" => 'Cada pergunta deve ter entre 2 e 6 opções.',
                ]);
            }

            $correct = collect($options)
                ->filter(fn (array $option): bool => $this->optionIsCorrect($option))
                ->count();

            if ($correct !== 1) {
                throw ValidationException::withMessages([
                    "questions.$index.options" => 'Cada pergunta deve ter exactamente uma opção correcta.',
                ]);
            }
        }
    }

    private function optionIsCorrect(array $option): bool
    {
        return filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
