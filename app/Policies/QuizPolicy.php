<?php

namespace App\Policies;

use App\Enums\QuizStatus;
use App\Models\Quiz;
use App\Models\User;
use App\Policies\Concerns\ChecksRoles;

class QuizPolicy
{
    use ChecksRoles;

    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    public function view(?User $user, Quiz $quiz): bool
    {
        if ($user === null) {
            return false;
        }

        return $quiz->status === QuizStatus::PUBLICADO || $this->isModeratorOrAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function attempt(User $user, Quiz $quiz): bool
    {
        return $quiz->status === QuizStatus::PUBLICADO && $this->isResidentOrAdmin($user);
    }
}
