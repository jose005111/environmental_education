<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    private const NAMES = [
        'Lixo',
        'Saneamento',
        'Água',
        'Erosão',
        'Reciclagem',
        'Compostagem',
        'Energia',
        'Biodiversidade',
        'Mata Atlântica',
    ];

    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => self::availableName(),
            'slug' => null,
            'description' => 'Tema ambiental do Bairro dos Navegantes.',
        ];
    }

    private static function availableName(): string
    {
        $available = array_diff(self::NAMES, Category::pluck('name')->all());

        if ($available === []) {
            return 'Tema '.fake()->unique()->numberBetween(100, 999999);
        }

        return fake()->randomElement($available);
    }
}
