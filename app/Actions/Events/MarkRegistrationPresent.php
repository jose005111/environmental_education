<?php

namespace App\Actions\Events;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MarkRegistrationPresent
{
    public function __invoke(EventRegistration $registration): EventRegistration
    {
        return DB::transaction(function () use ($registration): EventRegistration {
            $lockedRegistration = EventRegistration::query()
                ->whereKey($registration->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $event = Event::query()
                ->whereKey($lockedRegistration->event_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRegistration->status === RegistrationStatus::CANCELADO) {
                throw new ConflictHttpException('Não é possível marcar presença numa inscrição cancelada.');
            }

            if (now()->lessThan($event->starts_at)) {
                throw new ConflictHttpException('A presença só pode ser marcada a partir do início do evento.');
            }

            $lockedRegistration->status = RegistrationStatus::PRESENTE;
            $lockedRegistration->save();

            return $lockedRegistration;
        });
    }
}
