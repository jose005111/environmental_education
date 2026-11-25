<?php

namespace App\Enums;

enum EventStatus: string
{
    case RASCUNHO = 'rascunho';
    case PUBLICADO = 'publicado';
    case CANCELADO = 'cancelado';
    case CONCLUIDO = 'concluido';

    public function label(): string
    {
        return match ($this) {
            self::RASCUNHO => 'Rascunho',
            self::PUBLICADO => 'Publicado',
            self::CANCELADO => 'Cancelado',
            self::CONCLUIDO => 'Concluído',
        };
    }
}
