<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportUpdate>
 */
class ReportUpdateFactory extends Factory
{
    protected $model = ReportUpdate::class;

    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'user_id' => User::factory(),
            'from_status' => null,
            'to_status' => ReportStatus::RECEBIDO,
            'note' => null,
        ];
    }

    public function transition(ReportStatus $from, ReportStatus $to, ?string $note = null): static
    {
        return $this->state(fn (array $attributes) => [
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }
}
