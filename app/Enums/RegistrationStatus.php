<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case INSCRITO = 'inscrito';
    case CANCELADO = 'cancelado';
    case PRESENTE = 'presente';

    public function label(): string
    {
        return match ($this) {
            self::INSCRITO => 'Inscrito',
            self::CANCELADO => 'Cancelado',
            self::PRESENTE => 'Presente',
        };
    }
}
