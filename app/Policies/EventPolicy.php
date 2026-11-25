<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Policies\Concerns\ChecksRoles;

class EventPolicy
{
    use ChecksRoles;

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Event $event): bool
    {
        return in_array($event->status, [
            EventStatus::PUBLICADO,
            EventStatus::CANCELADO,
            EventStatus::CONCLUIDO,
        ], true) || ($user !== null && $this->isModeratorOrAdmin($user));
    }

    public function create(User $user): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function register(User $user, Event $event): bool
    {
        return $event->status === EventStatus::PUBLICADO && $this->isResidentOrAdmin($user);
    }

    public function cancelRegistration(User $user, Event $event): bool
    {
        return $this->isResidentOrAdmin($user);
    }

    public function viewRegistrations(User $user, Event $event): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function markPresent(User $user, EventRegistration $registration): bool
    {
        return $this->isModeratorOrAdmin($user);
    }
}
