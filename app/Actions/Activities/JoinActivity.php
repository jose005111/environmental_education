<?php

namespace App\Actions\Activities;

use App\Enums\ActivityStatus;
use App\Enums\ParticipationStatus;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class JoinActivity
{
    public function execute(User $user, Activity $activity): ActivityParticipation
    {
        return DB::transaction(function () use ($user, $activity): ActivityParticipation {
            $locked = Activity::query()
                ->whereKey($activity->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ActivityStatus::ABERTA) {
                throw new DomainException('Esta actividade não está aberta para inscrições.');
            }

            $today = now()->toDateString();
            $startsOn = $locked->starts_on?->toDateString();
            $endsOn = $locked->ends_on?->toDateString();

            if (($startsOn !== null && $startsOn > $today) || ($endsOn !== null && $endsOn < $today)) {
                throw new DomainException('A actividade não está dentro do período de inscrição.');
            }

            $existing = ActivityParticipation::query()
                ->where('activity_id', $locked->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new DomainException('Já existe uma inscrição para esta actividade.');
            }

            return ActivityParticipation::query()->create([
                'activity_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'status' => ParticipationStatus::INSCRITO,
            ]);
        });
    }
}
