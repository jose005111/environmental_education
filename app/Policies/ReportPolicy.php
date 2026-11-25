<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Policies\Concerns\ChecksRoles;

class ReportPolicy
{
    use ChecksRoles;

    public function viewAny(User $user): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function viewPublic(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Report $report): bool
    {
        if ($report->status === ReportStatus::RESOLVIDO && $report->is_public) {
            return true;
        }

        return $user !== null
            && ($this->owns($user, $report->user_id) || $this->isModeratorOrAdmin($user));
    }

    public function create(User $user): bool
    {
        return $this->isResidentOrAdmin($user);
    }

    public function update(User $user, Report $report): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function assign(User $user, Report $report): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function changeStatus(User $user, Report $report): bool
    {
        return $this->isModeratorOrAdmin($user);
    }

    public function delete(User $user, Report $report): bool
    {
        return $this->isModeratorOrAdmin($user);
    }
}
