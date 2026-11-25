<?php

namespace App\Actions\Activities;

use App\Enums\ParticipationStatus;
use App\Models\ActivityParticipation;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReviewActivityParticipation
{
    public function execute(
        User $reviewer,
        ActivityParticipation $participation,
        array $data,
    ): ActivityParticipation {
        if ($reviewer->getKey() === $participation->user_id) {
            throw new DomainException('Não é possível rever a própria participação.');
        }

        $status = $data['status'] ?? null;
        $status = $status instanceof ParticipationStatus ? $status->value : (string) $status;

        if (! in_array($status, [ParticipationStatus::VALIDADO->value, ParticipationStatus::REJEITADO->value], true)) {
            throw new DomainException('O resultado da revisão é inválido.');
        }

        return DB::transaction(function () use ($reviewer, $participation, $data, $status): ActivityParticipation {
            $locked = ActivityParticipation::query()
                ->whereKey($participation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ParticipationStatus::SUBMETIDO) {
                throw new DomainException('Apenas participações submetidas podem ser revistas.');
            }

            $locked->fill([
                'status' => ParticipationStatus::from($status),
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_note' => array_key_exists('review_note', $data) ? $data['review_note'] : null,
            ])->save();

            return $locked;
        });
    }
}
