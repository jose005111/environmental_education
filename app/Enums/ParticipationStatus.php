<?php

namespace App\Enums;

enum ParticipationStatus: string
{
    case INSCRITO = 'inscrito';
    case SUBMETIDO = 'submetido';
    case VALIDADO = 'validado';
    case REJEITADO = 'rejeitado';

    public function label(): string
    {
        return match ($this) {
            self::INSCRITO => 'Inscrito',
            self::SUBMETIDO => 'Submetido',
            self::VALIDADO => 'Validado',
            self::REJEITADO => 'Rejeitado',
        };
    }
}
