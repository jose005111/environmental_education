<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ChangeReportStatus
{
    public function execute(
        Report $report,
        User $actor,
        ReportStatus|string $status,
        ?string $note = null,
        ?bool $isPublic = null,
    ): Report {
        $status = $status instanceof ReportStatus ? $status : ReportStatus::tryFrom($status);

        if ($status === null) {
            throw ValidationException::withMessages([
                'status' => 'O estado indicado é inválido.',
            ]);
        }

        if ($isPublic !== null && ! $actor->isModerator() && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'is_public' => 'Sem permissão para alterar a visibilidade do reporte.',
            ]);
        }

        return DB::transaction(function () use ($report, $actor, $status, $note, $isPublic): Report {
            $report = Report::query()
                ->whereKey($report->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->isTransitionAllowed($report->status, $status, $actor)) {
                throw ValidationException::withMessages([
                    'status' => "Transição inválida de {$report->status->label()} para {$status->label()}.",
                ]);
            }

            $report->updates()->create([
                'user_id' => $actor->getKey(),
                'from_status' => $report->status,
                'to_status' => $status,
                'note' => $note,
            ]);

            $report->status = $status;
            $report->resolved_at = $status === ReportStatus::RESOLVIDO ? now() : null;

            if ($isPublic !== null) {
                $report->is_public = $isPublic;
            }

            $report->save();

            return $report;
        });
    }

    public function __invoke(
        Report $report,
        User $actor,
        ReportStatus|string $status,
        ?string $note = null,
        ?bool $isPublic = null,
    ): Report {
        return $this->execute($report, $actor, $status, $note, $isPublic);
    }

    private function isTransitionAllowed(ReportStatus $from, ReportStatus $to, User $actor): bool
    {
        return match ($from) {
            ReportStatus::RECEBIDO => in_array($to, [ReportStatus::EM_ANALISE, ReportStatus::REJEITADO], true),
            ReportStatus::EM_ANALISE => in_array($to, [ReportStatus::RESOLVIDO, ReportStatus::REJEITADO], true),
            ReportStatus::RESOLVIDO, ReportStatus::REJEITADO => $actor->isAdmin() && $to === ReportStatus::EM_ANALISE,
        };
    }
}
