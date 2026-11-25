<?php

namespace App\Enums;

enum ContentType: string
{
    case ARTIGO = 'artigo';
    case DICA = 'dica';
    case VIDEO = 'video';
    case INFOGRAFICO = 'infografico';

    public function label(): string
    {
        return match ($this) {
            self::ARTIGO => 'Artigo',
            self::DICA => 'Dica',
            self::VIDEO => 'Vídeo',
            self::INFOGRAFICO => 'Infográfico',
        };
    }
}
