<?php

namespace App\Actions\Quizzes;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateQuiz
{
    use ValidatesQuizStructure;

    public function execute(Quiz $quiz, array $data): Quiz
    {
        if (array_key_exists('questions', $data)) {
            $this->validateQuestions($data['questions']);
            $this->validateProvidedIds($quiz, $data['questions']);
        }

        return DB::transaction(function () use ($quiz, $data): Quiz {
            $quiz->fill(Arr::only($data, ['category_id', 'title', 'description', 'status']))->save();

            if (! array_key_exists('questions', $data)) {
                return $quiz->load('category', 'questions.options');
            }

            $existing = $quiz->questions()->with('options')->get();
            $keptQuestionIds = [];

            foreach ($data['questions'] as $index => $questionData) {
                $question = isset($questionData['id'])
                    ? $existing->firstWhere('id', $questionData['id'])
                    : null;

                if ($question === null) {
                    $question = $quiz->questions()->create([
                        'statement' => $questionData['statement'],
                        'explanation' => $questionData['explanation'] ?? null,
                        'position' => $questionData['position'] ?? $index,
                    ]);
                } else {
                    $question->fill(Arr::only($questionData, ['statement', 'explanation', 'position']))->save();
                }

                $keptQuestionIds[] = $question->id;
                $this->syncOptions($question, $questionData['options'] ?? []);
            }

            foreach ($existing as $question) {
                if (in_array($question->id, $keptQuestionIds, true)) {
                    continue;
                }

                if ($this->questionHasAnswers($question)) {
                    throw new QuizConflictException('Não é possível remover perguntas com respostas registadas.');
                }

                $question->delete();
            }

            return $quiz->load('category', 'questions.options');
        });
    }

    private function validateProvidedIds(Quiz $quiz, array $questions): void
    {
        $questionIds = $quiz->questions()->pluck('id')->all();

        foreach ($questions as $index => $question) {
            if (isset($question['id']) && ! in_array($question['id'], $questionIds)) {
                throw ValidationException::withMessages([
                    "questions.$index.id" => 'A pergunta não pertence a este questionário.',
                ]);
            }

            if (! isset($question['id'])) {
                continue;
            }

            $optionIds = $quiz->questions()->find($question['id'])->options()->pluck('id')->all();

            foreach ($question['options'] ?? [] as $optionIndex => $option) {
                if (isset($option['id']) && ! in_array($option['id'], $optionIds)) {
                    throw ValidationException::withMessages([
                        "questions.$index.options.$optionIndex.id" => 'A opção não pertence a esta pergunta.',
                    ]);
                }
            }
        }
    }

    private function syncOptions(QuizQuestion $question, array $options): void
    {
        $existing = $question->options()->get();
        $keptOptionIds = [];

        foreach ($options as $index => $optionData) {
            $option = isset($optionData['id'])
                ? $existing->firstWhere('id', $optionData['id'])
                : null;

            if ($option === null) {
                $option = $question->options()->create([
                    'text' => $optionData['text'],
                    'is_correct' => $this->optionIsCorrect($optionData),
                    'position' => $optionData['position'] ?? $index,
                ]);
            } else {
                $option->fill([
                    'text' => $optionData['text'],
                    'is_correct' => $this->optionIsCorrect($optionData),
                    'position' => $optionData['position'] ?? $index,
                ])->save();
            }

            $keptOptionIds[] = $option->id;
        }

        foreach ($existing as $option) {
            if (in_array($option->id, $keptOptionIds, true)) {
                continue;
            }

            if ($option->answers()->exists()) {
                throw new QuizConflictException('Não é possível remover opções com respostas registadas.');
            }

            $option->delete();
        }
    }

    private function questionHasAnswers(QuizQuestion $question): bool
    {
        return $question->answers()->exists()
            || $question->options()->whereHas('answers')->exists();
    }
}
