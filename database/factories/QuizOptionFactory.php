<?php

namespace Database\Factories;

use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Database\Factories\Concerns\PortugueseText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizOption>
 */
class QuizOptionFactory extends Factory
{
    use PortugueseText;

    protected $model = QuizOption::class;

    public function definition(): array
    {
        return [
            'quiz_question_id' => QuizQuestion::factory(),
            'text' => $this->ptSentence(),
            'is_correct' => false,
            'position' => 0,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }
}
