<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    private const NAMES = [
        'Centro',
        'Zona Norte',
        'Zona Sul',
        'Zona Leste',
        'Zona Oeste',
        'Orla',
        'Mercado',
        'Vila Nova',
    ];

    protected $model = Zone::class;

    public function definition(): array
    {
        return [
            'name' => self::availableName(),
            'description' => fake()->boolean(70) ? 'Zona do Bairro dos Navegantes.' : null,
        ];
    }

    private static function availableName(): string
    {
        $available = array_diff(self::NAMES, Zone::pluck('name')->all());

        if ($available === []) {
            return 'Zona '.fake()->unique()->numberBetween(100, 999999);
        }

        return fake()->randomElement($available);
    }
}
