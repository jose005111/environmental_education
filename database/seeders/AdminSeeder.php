<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', RoleName::ADMINISTRADOR->value)->firstOrFail();

        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@ambiente.local')],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'role_id' => $role->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );
    }
}
