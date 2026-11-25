<?php

namespace Database\Factories;

use App\Enums\QuizStatus;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Database\Factories\Concerns\PortugueseText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    use PortugueseText;

    protected $model = Quiz::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'author_id' => User::factory(),
            'title' => $this->ptTitle(),
            'description' => $this->ptSentence(),
            'status' => QuizStatus::RASCUNHO,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuizStatus::PUBLICADO,
        ]);
    }

    public function withQuestions(int $count = 2): static
    {
        return $this->afterCreating(function (Quiz $quiz) use ($count): void {
            QuizQuestion::factory()
                ->count($count)
                ->for($quiz)
                ->create()
                ->each(function (QuizQuestion $question): void {
                    QuizOption::factory()->for($question, 'question')->correct()->create(['position' => 1]);
                    QuizOption::factory()->count(3)->for($question, 'question')->create(['position' => 2]);
                });
        });
    }
}
