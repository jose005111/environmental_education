<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    private const CATEGORIES = [
        'Lixo' => 'Resíduos sólidos, coleta e descarte adequado.',
        'Saneamento' => 'Drenagem, esgoto e condições de higiene no bairro.',
        'Água' => 'Consumo consciente e preservação das fontes de água.',
        'Erosão' => 'Cuidados com o solo e prevenção de assoreamentos.',
        'Reciclagem' => 'Separação e reaproveitamento de materiais recicláveis.',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $description) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description],
            );
        }
    }
}
