<?php

namespace App\Actions\Events;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CancelEventRegistration
{
    public function __invoke(Event $event, User $user): EventRegistration
    {
        return DB::transaction(function () use ($event, $user): EventRegistration {
            Event::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $registration = EventRegistration::query()
                ->where('event_id', $event->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($registration->status === RegistrationStatus::CANCELADO) {
                throw new ConflictHttpException('Esta inscrição já foi cancelada.');
            }

            $registration->status = RegistrationStatus::CANCELADO;
            $registration->save();

            return $registration;
        });
    }
}
