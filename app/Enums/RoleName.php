<?php

namespace App\Enums;

enum RoleName: string
{
    case RESIDENTE = 'residente';
    case MODERADOR = 'moderador';
    case ADMINISTRADOR = 'administrador';

    public function label(): string
    {
        return match ($this) {
            self::RESIDENTE => 'Residente',
            self::MODERADOR => 'Moderador',
            self::ADMINISTRADOR => 'Administrador',
        };
    }
}
