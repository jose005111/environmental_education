<?php

namespace Tests\Concerns;

use App\Enums\RoleName;
use App\Models\User;

trait ActsAsRole
{
    protected function actingAsRole(RoleName|string $role): User
    {
        $roleName = $role instanceof RoleName ? $role : RoleName::from($role);

        $user = User::factory()->withRole($roleName)->create();

        $this->actingAs($user, 'sanctum');

        return $user;
    }
}
