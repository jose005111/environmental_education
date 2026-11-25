<?php

namespace Database\Factories;

use App\Enums\ParticipationStatus;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\User;
use Database\Factories\Concerns\PortugueseText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityParticipation>
 */
class ActivityParticipationFactory extends Factory
{
    use PortugueseText;

    protected $model = ActivityParticipation::class;

    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'user_id' => User::factory(),
            'status' => ParticipationStatus::INSCRITO,
            'notes' => null,
            'proof_photo' => null,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ParticipationStatus::SUBMETIDO,
            'notes' => $this->ptSentence(),
            'proof_photo' => 'activities/prova-exemplo.jpg',
            'submitted_at' => now(),
        ]);
    }

    public function validated(): static
    {
        return $this->submitted()->state(fn (array $attributes) => [
            'status' => ParticipationStatus::VALIDADO,
            'reviewed_by' => User::factory()->moderator(),
            'reviewed_at' => now(),
            'review_note' => 'Prova conferida com sucesso.',
        ]);
    }

    public function rejected(): static
    {
        return $this->submitted()->state(fn (array $attributes) => [
            'status' => ParticipationStatus::REJEITADO,
            'reviewed_by' => User::factory()->moderator(),
            'reviewed_at' => now(),
            'review_note' => 'A prova enviada não corresponde à actividade.',
        ]);
    }
}
