<?php

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\Category;
use App\Models\User;
use Database\Factories\Concerns\PortugueseText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    use PortugueseText;

    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'author_id' => User::factory(),
            'title' => $this->ptTitle(),
            'slug' => null,
            'summary' => $this->ptSentence(),
            'instructions' => $this->ptParagraph(),
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDays(7)->toDateString(),
            'status' => ActivityStatus::RASCUNHO,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ActivityStatus::ABERTA,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ActivityStatus::ENCERRADA,
        ]);
    }

    public function withParticipations(int $count = 3): static
    {
        return $this->afterCreating(function (Activity $activity) use ($count): void {
            ActivityParticipation::factory()
                ->count($count)
                ->for($activity)
                ->create();
        });
    }
}
