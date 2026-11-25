<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\Content;
use App\Models\User;
use App\Policies\Concerns\ChecksRoles;

class ContentPolicy
{
    use ChecksRoles;

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Content $content): bool
    {
        return $content->status === ContentStatus::PUBLICADO
            || ($user !== null && $this->isModeratorOrAdmin($user));
    }

    public function create(User $user): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function update(User $user, Content $content): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function publish(User $user, Content $content): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function delete(User $user, Content $content): bool
    {
        return $this->isModeratorOrAdmin($user);
    }
}
