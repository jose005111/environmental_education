<?php

namespace App\Enums;

enum ReportStatus: string
{
    case RECEBIDO = 'recebido';
    case EM_ANALISE = 'em_analise';
    case RESOLVIDO = 'resolvido';
    case REJEITADO = 'rejeitado';

    public function label(): string
    {
        return match ($this) {
            self::RECEBIDO => 'Recebido',
            self::EM_ANALISE => 'Em análise',
            self::RESOLVIDO => 'Resolvido',
            self::REJEITADO => 'Rejeitado',
        };
    }
}
