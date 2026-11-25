<?php

namespace App\Enums;

enum ContentStatus: string
{
    case RASCUNHO = 'rascunho';
    case PUBLICADO = 'publicado';

    public function label(): string
    {
        return match ($this) {
            self::RASCUNHO => 'Rascunho',
            self::PUBLICADO => 'Publicado',
        };
    }
}
