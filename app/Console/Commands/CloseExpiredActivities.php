<?php

namespace App\Console\Commands;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use Illuminate\Console\Command;

final class CloseExpiredActivities extends Command
{
    protected $signature = 'activities:close-expired';

    protected $description = 'Encerra as atividades abertas cujo prazo já passou';

    public function handle(): int
    {
        $count = Activity::query()
            ->where('status', ActivityStatus::ABERTA)
            ->where('ends_on', '<', today()->toDateString())
            ->update(['status' => ActivityStatus::ENCERRADA]);

        $this->info("{$count} atividade(s) encerrada(s).");

        return self::SUCCESS;
    }
}
