<?php

namespace App\Policies;

use App\Enums\ActivityStatus;
use App\Enums\ParticipationStatus;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\User;
use App\Policies\Concerns\ChecksRoles;

class ActivityPolicy
{
    use ChecksRoles;

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Activity $activity): bool
    {
        return in_array($activity->status, [ActivityStatus::ABERTA, ActivityStatus::ENCERRADA], true)
            || ($user !== null && $this->isModeratorOrAdmin($user));
    }

    public function create(User $user): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function update(User $user, Activity $activity): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function join(User $user, Activity $activity): bool
    {
        return $activity->status === ActivityStatus::ABERTA && $this->isResidentOrAdmin($user);
    }

    public function submit(User $user, ActivityParticipation $participation): bool
    {
        return $this->owns($user, $participation->user_id)
            && in_array($participation->status, [ParticipationStatus::INSCRITO, ParticipationStatus::REJEITADO], true);
    }

    public function review(User $user, ActivityParticipation $participation): bool
    {
        return $this->isModeratorOrAdmin($user)
            && $participation->status === ParticipationStatus::SUBMETIDO
            && ! $this->owns($user, $participation->user_id);
    }
}
