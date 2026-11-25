<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\Zone;
use Database\Factories\Concerns\PortugueseText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    use PortugueseText;

    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::inRandomOrder()->value('id') ?? Category::factory(),
            'zone_id' => Zone::inRandomOrder()->value('id') ?? Zone::factory(),
            'author_id' => User::factory(),
            'title' => $this->ptTitle(),
            'slug' => null,
            'description' => $this->ptParagraph(),
            'location' => 'Praça central do Bairro dos Navegantes',
            'starts_at' => now()->addDays(3)->setTime(9, 0),
            'ends_at' => now()->addDays(3)->setTime(12, 0),
            'capacity' => 30,
            'cover_image' => null,
            'status' => EventStatus::RASCUNHO,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::PUBLICADO,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::CANCELADO,
        ]);
    }

    public function concluded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::CONCLUIDO,
        ]);
    }

    public function withRegistrations(int $count = 3): static
    {
        return $this->afterCreating(function (Event $event) use ($count): void {
            EventRegistration::factory()
                ->count($count)
                ->for($event)
                ->create();
        });
    }
}
