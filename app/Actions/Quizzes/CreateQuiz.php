<?php

namespace App\Actions\Quizzes;

use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateQuiz
{
    use ValidatesQuizStructure;

    public function execute(array $data, User $author): Quiz
    {
        $questions = $data['questions'] ?? [];

        $this->validateQuestions($questions);

        return DB::transaction(function () use ($data, $author, $questions): Quiz {
            $quiz = Quiz::create([
                'category_id' => $data['category_id'],
                'author_id' => $author->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? QuizStatus::RASCUNHO->value,
            ]);

            foreach ($questions as $index => $questionData) {
                $question = $quiz->questions()->create([
                    'statement' => $questionData['statement'],
                    'explanation' => $questionData['explanation'] ?? null,
                    'position' => $questionData['position'] ?? $index,
                ]);

                foreach ($questionData['options'] as $optionIndex => $optionData) {
                    $question->options()->create([
                        'text' => $optionData['text'],
                        'is_correct' => $this->optionIsCorrect($optionData),
                        'position' => $optionData['position'] ?? $optionIndex,
                    ]);
                }
            }

            return $quiz->load('category', 'questions.options');
        });
    }
}
