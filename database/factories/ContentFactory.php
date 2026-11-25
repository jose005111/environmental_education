<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ContentType;
use App\Models\Category;
use App\Models\Content;
use App\Models\User;
use Database\Factories\Concerns\PortugueseText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Content>
 */
class ContentFactory extends Factory
{
    use PortugueseText;

    protected $model = Content::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'author_id' => User::factory(),
            'title' => $this->ptTitle(),
            'slug' => null,
            'summary' => $this->ptSentence(),
            'body' => $this->ptParagraph(),
            'type' => fake()->randomElement(ContentType::cases()),
            'cover_image' => null,
            'media_url' => null,
            'status' => ContentStatus::RASCUNHO,
            'published_at' => null,
            'views_count' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::PUBLICADO,
            'published_at' => now()->subDays(rand(1, 10)),
        ]);
    }

    public function withCoverImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'cover_image' => 'covers/conteudo-exemplo.jpg',
        ]);
    }
}
