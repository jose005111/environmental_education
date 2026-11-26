<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventRegistrationResource;
use App\Http\Resources\ParticipationResource;
use App\Http\Resources\QuizAttemptResource;
use App\Http\Resources\ReportSummaryResource;
use App\Http\Resources\ResidentSearchResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResidentOverviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->merge(['search' => trim((string) $request->input('search', ''))]);
        $data = $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $search = $data['search'];
        $residents = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'residente'))
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$this->escapeLike($search).'%')
                    ->orWhere('email', 'like', '%'.$this->escapeLike($search).'%')
                    ->orWhere('phone', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->with('zone')
            ->orderBy('name')
            ->paginate(10);

        return $this->paginated($residents, ResidentSearchResource::class, $request);
    }

    public function show(User $resident): JsonResponse
    {
        $this->ensureResident($resident);

        $resident->load('role', 'zone')->loadCount([
            'quizAttempts', 'activityParticipations', 'eventRegistrations', 'reports',
        ]);

        return $this->success('Morador encontrado.', [
            'resident' => new UserResource($resident),
            'created_at' => $resident->created_at?->toJSON(),
            'counts' => [
                'quizzes' => $resident->quiz_attempts_count,
                'activities' => $resident->activity_participations_count,
                'events' => $resident->event_registrations_count,
                'reports' => $resident->reports_count,
            ],
        ]);
    }

    public function records(Request $request, User $resident): JsonResponse
    {
        $this->ensureResident($resident);
        $data = $request->validate([
            'type' => ['required', Rule::in(['quizzes', 'activities', 'events', 'reports'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        [$query, $resource] = match ($data['type']) {
            'quizzes' => [$resident->quizAttempts()->with(['quiz', 'answers.question', 'answers.option'])->orderByDesc('created_at'), QuizAttemptResource::class],
            'activities' => [$resident->activityParticipations()->with(['activity.category', 'reviewer'])->orderByDesc('created_at'), ParticipationResource::class],
            'events' => [$resident->eventRegistrations()->with(['event.category', 'event.zone'])->orderByDesc('created_at'), EventRegistrationResource::class],
            'reports' => [$resident->reports()->with(['zone', 'category', 'assignee'])->orderByDesc('created_at'), ReportSummaryResource::class],
            default => throw new InvalidArgumentException('Tipo de registo inválido.'),
        };

        return $this->paginated($query->paginate(20), $resource, $request);
    }

    private function ensureResident(User $resident): void
    {
        if (! $resident->isResident()) {
            throw new NotFoundHttpException;
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
