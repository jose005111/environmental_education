<?php

namespace App\Actions\Activities;

use App\Enums\ParticipationStatus;
use App\Models\ActivityParticipation;
use App\Models\User;
use App\Services\StoredFile;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class SubmitActivityProof
{
    public function __construct(private StoredFile $files) {}

    public function execute(
        User $user,
        ActivityParticipation $participation,
        array $data,
        ?UploadedFile $proofPhoto,
    ): ActivityParticipation {
        if ($proofPhoto === null) {
            throw new DomainException('A foto de prova é obrigatória.');
        }

        if ($participation->user_id !== $user->getKey()
            || ! in_array($participation->status, [ParticipationStatus::INSCRITO, ParticipationStatus::REJEITADO], true)) {
            throw new DomainException('Não é possível submeter prova nesta participação.');
        }

        $stored = $this->files->store($proofPhoto, 'activities');

        try {
            return DB::transaction(function () use ($participation, $data, $stored): ActivityParticipation {
                $locked = ActivityParticipation::query()
                    ->whereKey($participation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($locked->status, [ParticipationStatus::INSCRITO, ParticipationStatus::REJEITADO], true)) {
                    throw new DomainException('Não é possível submeter prova nesta participação.');
                }

                $previousPhoto = $locked->proof_photo;

                $locked->fill([
                    'status' => ParticipationStatus::SUBMETIDO,
                    'notes' => array_key_exists('notes', $data) ? $data['notes'] : $locked->notes,
                    'proof_photo' => $stored['path'],
                    'submitted_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                ])->save();

                if ($previousPhoto !== null && $previousPhoto !== $stored['path']) {
                    $this->files->delete($previousPhoto);
                }

                return $locked;
            });
        } catch (Throwable $exception) {
            $this->files->delete($stored['path']);

            throw $exception;
        }
    }
}
