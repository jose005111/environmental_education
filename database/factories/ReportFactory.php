<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportPhoto;
use App\Models\User;
use App\Models\Zone;
use Database\Factories\Concerns\PortugueseText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    use PortugueseText;

    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'zone_id' => Zone::inRandomOrder()->value('id') ?? Zone::factory(),
            'category_id' => Category::inRandomOrder()->value('id') ?? Category::factory(),
            'assigned_to' => null,
            'title' => $this->ptTitle(),
            'description' => $this->ptParagraph(),
            'location_note' => 'Próximo à praça central',
            'latitude' => fake()->latitude(-23, -22),
            'longitude' => fake()->longitude(-44, -43),
            'status' => ReportStatus::RECEBIDO,
            'is_public' => false,
            'resolved_at' => null,
        ];
    }

    public function inAnalysis(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::EM_ANALISE,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::RESOLVIDO,
            'is_public' => true,
            'resolved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::REJEITADO,
            'is_public' => false,
        ]);
    }

    public function withPhotos(int $count = 2): static
    {
        return $this->afterCreating(function (Report $report) use ($count): void {
            ReportPhoto::factory()
                ->count($count)
                ->for($report)
                ->create();
        });
    }
}
