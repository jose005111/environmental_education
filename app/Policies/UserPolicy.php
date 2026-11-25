<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksRoles;

class UserPolicy
{
    use ChecksRoles;

    public function viewAny(User $user): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function view(User $user, User $target): bool
    {
        return $this->owns($user, $target->getKey()) || $this->isModeratorOrAdmin($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->owns($user, $target->getKey()) || $user->isAdmin();
    }

    public function manage(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function changeRole(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function toggleActive(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin();
    }
}
