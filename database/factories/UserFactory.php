<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake('pt_BR')->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => self::roleId(RoleName::RESIDENTE),
            'zone_id' => Zone::inRandomOrder()->value('id') ?? Zone::factory(),
            'phone' => fake('pt_BR')->phoneNumber(),
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withRole(RoleName $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => self::roleId($role),
        ]);
    }

    public function moderator(): static
    {
        return $this->withRole(RoleName::MODERADOR);
    }

    public function admin(): static
    {
        return $this->withRole(RoleName::ADMINISTRADOR);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    private static function roleId(RoleName $role): int|RoleFactory
    {
        $roleId = Role::where('name', $role->value)->value('id');

        if ($roleId !== null) {
            return (int) $roleId;
        }

        return Role::factory()->state([
            'name' => $role,
            'label' => $role->label(),
        ]);
    }
}
