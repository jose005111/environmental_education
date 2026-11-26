<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Activities\JoinActivity;
use App\Actions\Activities\ReviewActivityParticipation;
use App\Actions\Activities\SubmitActivityProof;
use App\Enums\ActivityStatus;
use App\Enums\ParticipationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequest;
use App\Http\Requests\Api\V1\ReviewParticipationRequest;
use App\Http\Requests\Api\V1\StoreActivityRequest;
use App\Http\Requests\Api\V1\SubmitActivityProofRequest;
use App\Http\Requests\Api\V1\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\ActivitySummaryResource;
use App\Http\Resources\ParticipationResource;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Services\StoredFile;
use App\Support\QueryFilter;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ActivityController extends Controller
{
    public function __construct(
        private readonly JoinActivity $joinActivity,
        private readonly SubmitActivityProof $submitActivityProof,
        private readonly ReviewActivityParticipation $reviewActivityParticipation,
    ) {}

    public function index(IndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Activity::class);

        $filter = new QueryFilter(
            $request,
            filters: [
                'category' => fn (Builder $query, mixed $value) => $query->whereIn('category_id', QueryFilter::parseValues($value)),
                'status' => fn (Builder $query, mixed $value) => $query->whereIn('status', QueryFilter::parseValues($value)),
            ],
            sorts: [
                'title' => 'title',
                'starts_on' => 'starts_on',
                'created_at' => 'created_at',
            ],
            search: ['title', 'summary', 'instructions'],
            defaultSort: '-created_at',
        );

        $query = Activity::query()->with(['category', 'author', 'photos']);

        if (! $request->user()?->isModerator() && ! $request->user()?->isAdmin()) {
            $query->whereIn('status', [ActivityStatus::ABERTA->value, ActivityStatus::ENCERRADA->value]);
        }

        $activities = $filter->apply($query)
            ->paginate($filter->perPage());

        return $this->paginated($activities, ActivitySummaryResource::class, $request);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $query = Activity::query()
            ->where('slug', $slug)
            ->with(['category', 'author', 'photos'])
            ->withCount('participations');

        if (! $request->user()?->isModerator() && ! $request->user()?->isAdmin()) {
            $query->whereIn('status', [ActivityStatus::ABERTA->value, ActivityStatus::ENCERRADA->value]);
        }

        $activity = $query->firstOrFail();

        Gate::authorize('view', $activity);

        return $this->success('Actividade encontrada.', new ActivityResource($activity));
    }

    public function store(StoreActivityRequest $request): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        $data = $request->safe()->except('photos');
        $stored = [];
        try {
            $activity = DB::transaction(function () use ($request, $data, &$stored): Activity {
                $activity = Activity::create([...$data, 'author_id' => $request->user()->getKey()]);
                foreach ($request->file('photos', []) as $photo) {
                    $path = app(StoredFile::class)->store($photo, 'activities')['path'];
                    $stored[] = $path;
                    $activity->photos()->create(['path' => $path]);
                }

                return $activity;
            });
        } catch (Throwable $exception) {
            foreach ($stored as $path) {
                app(StoredFile::class)->delete($path);
            }
            throw $exception;
        }

        return $this->created(
            new ActivityResource($this->loadActivity($activity)),
            'Actividade criada com sucesso.',
        );
    }

    public function update(UpdateActivityRequest $request, Activity $activity): JsonResponse
    {
        Gate::authorize('update', $activity);

        $data = $request->safe()->except(['photos', 'removed_photo_ids']);
        $removed = $activity->photos()->whereIn('id', $request->input('removed_photo_ids', []))->get();
        if ($activity->photos()->count() - $removed->count() + count($request->file('photos', [])) > 5) {
            throw ValidationException::withMessages(['photos' => 'A actividade pode ter no máximo 5 fotografias.']);
        }

        $stored = [];
        try {
            DB::transaction(function () use ($activity, $data, $removed, $request, &$stored): void {
                $activity->fill($data)->save();
                $activity->photos()->whereIn('id', $removed->modelKeys())->delete();
                foreach ($request->file('photos', []) as $photo) {
                    $path = app(StoredFile::class)->store($photo, 'activities')['path'];
                    $stored[] = $path;
                    $activity->photos()->create(['path' => $path]);
                }
            });
        } catch (Throwable $exception) {
            foreach ($stored as $path) {
                app(StoredFile::class)->delete($path);
            }
            throw $exception;
        }

        foreach ($removed as $photo) {
            app(StoredFile::class)->delete($photo->path);
        }

        return $this->success(
            'Actividade actualizada com sucesso.',
            new ActivityResource($this->loadActivity($activity)),
        );
    }

    public function destroy(Activity $activity): HttpResponse|JsonResponse
    {
        Gate::authorize('delete', $activity);

        if ($activity->participations()->exists()) {
            return $this->conflict('Não é possível apagar uma actividade com participações associadas.');
        }

        $activity->delete();

        return $this->noContent();
    }

    public function join(Request $request, Activity $activity): JsonResponse
    {
        Gate::authorize('join', $activity);

        try {
            $participation = $this->joinActivity->execute($request->user(), $activity);
        } catch (DomainException $exception) {
            return $this->conflict($exception->getMessage());
        }

        return $this->created(
            new ParticipationResource($participation->load(['activity.category', 'user'])),
            'Inscrição registada com sucesso.',
        );
    }

    public function submit(SubmitActivityProofRequest $request, Activity $activity): JsonResponse
    {
        $participation = $this->participationQuery()
            ->where('activity_id', $activity->getKey())
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();

        Gate::authorize('submit', $participation);

        try {
            $participation = $this->submitActivityProof->execute(
                $request->user(),
                $participation,
                $request->validated(),
                $request->file('proof_photo'),
            );
        } catch (DomainException $exception) {
            return $this->conflict($exception->getMessage());
        }

        return $this->success(
            'Prova submetida com sucesso.',
            new ParticipationResource($participation->load(['activity.category', 'user', 'reviewer'])),
        );
    }

    public function participations(IndexRequest $request): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        $filter = new QueryFilter(
            $request,
            filters: [
                'status' => fn (Builder $query, mixed $value) => $query->whereIn('status', QueryFilter::parseValues($value)),
                'activity' => fn (Builder $query, mixed $value) => $query->whereIn('activity_id', QueryFilter::parseValues($value)),
                'user' => fn (Builder $query, mixed $value) => $query->whereIn('user_id', QueryFilter::parseValues($value)),
            ],
            sorts: [
                'submitted_at' => 'submitted_at',
                'created_at' => 'created_at',
            ],
            search: ['notes', 'review_note'],
            defaultSort: '-submitted_at',
        );

        $query = $this->participationQuery();

        if (! $request->filled('filter.status')) {
            $query->where('status', ParticipationStatus::SUBMETIDO->value);
        }

        $participations = $filter->apply($query)->paginate($filter->perPage());

        return $this->paginated($participations, ParticipationResource::class, $request);
    }

    public function review(ReviewParticipationRequest $request, ActivityParticipation $participation): JsonResponse
    {
        Gate::authorize('review', $participation);

        try {
            $participation = $this->reviewActivityParticipation->execute(
                $request->user(),
                $participation,
                $request->validated(),
            );
        } catch (DomainException $exception) {
            return $this->conflict($exception->getMessage());
        }

        return $this->success(
            'Prova revista com sucesso.',
            new ParticipationResource($participation->load(['activity.category', 'user', 'reviewer'])),
        );
    }

    public function myParticipations(IndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Activity::class);

        $filter = new QueryFilter(
            $request,
            filters: [
                'status' => fn (Builder $query, mixed $value) => $query->whereIn('status', QueryFilter::parseValues($value)),
            ],
            sorts: [
                'submitted_at' => 'submitted_at',
                'created_at' => 'created_at',
            ],
            search: ['notes', 'review_note'],
            defaultSort: '-submitted_at',
        );

        $participations = $filter->apply($this->participationQuery()
            ->where('user_id', $request->user()->getKey()))
            ->paginate($filter->perPage());

        return $this->paginated($participations, ParticipationResource::class, $request);
    }

    /** @return Builder<ActivityParticipation> */
    private function participationQuery(): Builder
    {
        return ActivityParticipation::query()->with(['activity.category', 'user', 'reviewer']);
    }

    private function loadActivity(Activity $activity): Activity
    {
        return $activity->load(['category', 'author', 'photos'])->loadCount('participations');
    }
}
