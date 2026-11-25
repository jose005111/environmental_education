<?php

namespace App\Actions\Quizzes;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SubmitQuizAttempt
{
    public function execute(Quiz $quiz, User $user, array $answers): QuizAttempt
    {
        $questions = $quiz->questions()->with('options')->get()->keyBy('id');
        $errors = [];

        if (count($answers) !== $questions->count()) {
            $errors['answers'] = 'Deve responder a todas as perguntas do questionário.';
        }

        $grouped = collect($answers)->groupBy('question_id');

        foreach ($grouped as $group) {
            if ($group->count() > 1) {
                $errors['answers'] = 'Cada pergunta deve ser respondida apenas uma vez.';
            }
        }

        if ($grouped->keys()->diff($questions->keys())->isNotEmpty()) {
            $errors['answers'] = 'As respostas devem pertencer às perguntas deste questionário.';
        }

        $selected = [];
        $correctCount = 0;

        foreach ($answers as $index => $answer) {
            $question = $questions->get($answer['question_id']);

            if ($question === null) {
                continue;
            }

            $option = $question->options->firstWhere('id', $answer['option_id']);

            if ($option === null) {
                $errors["answers.$index.option_id"] = 'A opção indicada não pertence à pergunta.';

                continue;
            }

            $isCorrect = (bool) $option->is_correct;
            $correctCount += $isCorrect ? 1 : 0;
            $selected[] = [
                'question_id' => $question->id,
                'option_id' => $option->id,
                'is_correct' => $isCorrect,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($quiz, $user, $questions, $selected, $correctCount): QuizAttempt {
            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'total_questions' => $questions->count(),
                'correct_count' => $correctCount,
                'completed_at' => now(),
            ]);

            foreach ($selected as $answer) {
                $attempt->answers()->create([
                    'quiz_question_id' => $answer['question_id'],
                    'quiz_option_id' => $answer['option_id'],
                    'is_correct' => $answer['is_correct'],
                ]);
            }

            return $attempt->load('quiz', 'answers.question', 'answers.option');
        });
    }
}
