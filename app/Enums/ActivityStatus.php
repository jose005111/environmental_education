<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case RASCUNHO = 'rascunho';
    case ABERTA = 'aberta';
    case ENCERRADA = 'encerrada';

    public function label(): string
    {
        return match ($this) {
            self::RASCUNHO => 'Rascunho',
            self::ABERTA => 'Aberta',
            self::ENCERRADA => 'Encerrada',
        };
    }
}
