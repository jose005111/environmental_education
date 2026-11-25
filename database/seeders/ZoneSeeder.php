<?php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    private const ZONES = [
        'Centro' => 'Área central do Bairro dos Navegantes.',
        'Zona Norte' => 'Quadros ao norte da avenida principal.',
        'Zona Sul' => 'Área próxima à orla e à praça.',
        'Zona Leste' => 'Bairros residenciais a leste da escola.',
        'Zona Oeste' => 'Área de comércio e oficinas.',
    ];

    public function run(): void
    {
        foreach (self::ZONES as $name => $description) {
            Zone::firstOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }
}
