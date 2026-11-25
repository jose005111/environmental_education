<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RegisterForEvent
{
    public function __invoke(Event $event, User $user): EventRegistration
    {
        return DB::transaction(function () use ($event, $user): EventRegistration {
            $lockedEvent = Event::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedEvent->status !== EventStatus::PUBLICADO) {
                throw new ConflictHttpException('Este evento não aceita inscrições.');
            }

            if (! $lockedEvent->starts_at->isFuture()) {
                throw new ConflictHttpException('As inscrições para este evento já estão encerradas.');
            }

            $activeStatuses = [
                RegistrationStatus::INSCRITO->value,
                RegistrationStatus::PRESENTE->value,
            ];

            $registration = EventRegistration::query()
                ->where('event_id', $lockedEvent->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if ($registration !== null && in_array($registration->status?->value, $activeStatuses, true)) {
                throw new ConflictHttpException('Já está inscrito neste evento.');
            }

            if ($registration === null || $registration->status === RegistrationStatus::CANCELADO) {
                $active = EventRegistration::query()
                    ->where('event_id', $lockedEvent->getKey())
                    ->whereIn('status', $activeStatuses)
                    ->count();

                if ($lockedEvent->capacity !== null && $active >= $lockedEvent->capacity) {
                    throw new ConflictHttpException('Não existem vagas disponíveis para este evento.');
                }
            }

            if ($registration !== null) {
                $registration->status = RegistrationStatus::INSCRITO;
                $registration->save();

                return $registration;
            }

            try {
                return EventRegistration::query()->create([
                    'event_id' => $lockedEvent->getKey(),
                    'user_id' => $user->getKey(),
                    'status' => RegistrationStatus::INSCRITO,
                ]);
            } catch (QueryException $exception) {
                if ($this->isDuplicate($exception)) {
                    throw new ConflictHttpException('Já está inscrito neste evento.');
                }

                throw $exception;
            }
        });
    }

    private function isDuplicate(QueryException $exception): bool
    {
        return $exception->getCode() === '23000'
            || str_contains($exception->getMessage(), 'UNIQUE constraint failed')
            || str_contains($exception->getMessage(), 'Duplicate entry');
    }
}
