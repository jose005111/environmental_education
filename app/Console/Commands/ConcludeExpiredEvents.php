<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Console\Command;

final class ConcludeExpiredEvents extends Command
{
    protected $signature = 'events:conclude-expired';

    protected $description = 'Marca como concluídos os eventos publicados cujo termo já passou';

    public function handle(): int
    {
        $count = Event::query()
            ->where('status', EventStatus::PUBLICADO)
            ->where('ends_at', '<', now())
            ->update(['status' => EventStatus::CONCLUIDO]);

        $this->info("{$count} evento(s) concluído(s).");

        return self::SUCCESS;
    }
}
