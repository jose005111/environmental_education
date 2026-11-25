<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksRoles
{
    protected function isModeratorOrAdmin(User $user): bool
    {
        return $user->isModerator() || $user->isAdmin();
    }

    protected function isResidentOrAdmin(User $user): bool
    {
        return $user->isResident() || $user->isAdmin();
    }

    protected function owns(?User $user, int|string|null $ownerId): bool
    {
        return $user?->getKey() === $ownerId;
    }
}
