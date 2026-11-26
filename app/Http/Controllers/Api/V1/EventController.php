<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Events\CancelEventRegistration;
use App\Actions\Events\MarkRegistrationPresent;
use App\Actions\Events\RegisterForEvent;
use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequest;
use App\Http\Requests\Api\V1\StoreEventRequest;
use App\Http\Requests\Api\V1\UpdateEventRequest;
use App\Http\Resources\EventRegistrationResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventSummaryResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\StoredFile;
use App\Support\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EventController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Event::class);

        $filter = new QueryFilter(
            $request,
            filters: [
                'status' => $this->exactFilter('status'),
                'category' => $this->exactFilter('category_id'),
                'zone' => $this->exactFilter('zone_id'),
                'title' => ['column' => 'title', 'operator' => 'like'],
            ],
            sorts: [
                'starts_at' => 'starts_at',
                'ends_at' => 'ends_at',
                'title' => 'title',
                'created_at' => 'created_at',
            ],
            search: ['title', 'description', 'location'],
            defaultSort: '-starts_at',
        );

        $events = $filter->apply($this->eventQuery($request))->paginate($filter->perPage());

        return $this->paginated($events, EventSummaryResource::class, $request);
    }

    public function show(Request $request, string $slug): EventResource
    {
        $event = $this->eventQuery($request)
            ->with('author')
            ->where('slug', $slug)
            ->firstOrFail();

        Gate::authorize('view', $event);

        return new EventResource($event);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        Gate::authorize('create', Event::class);

        $data = $request->validated();
        $data['status'] ??= EventStatus::RASCUNHO;
        $data['author_id'] = $request->user()->getKey();
        $data = $this->withStoredCover($request, $data);

        $event = Event::query()->create($data);

        return $this->created(
            new EventResource($event->loadMissing(['category', 'zone', 'author'])),
            'Evento criado com sucesso.'
        );
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $previousCover = $event->cover_image;
        $data = $this->withStoredCover($request, $request->validated());

        $event->fill($data)->save();

        if ($request->hasFile('cover') && $previousCover !== null && $previousCover !== $event->cover_image) {
            app(StoredFile::class)->delete($previousCover);
        }

        return $this->success(
            'Evento actualizado com sucesso.',
            new EventResource($event->loadMissing(['category', 'zone', 'author']))
        );
    }

    public function destroy(Event $event): HttpResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        return $this->noContent();
    }

    public function register(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('register', $event);

        try {
            $registration = app(RegisterForEvent::class)($event, $request->user());
        } catch (ConflictHttpException $exception) {
            return $this->conflict($exception->getMessage());
        }

        return $this->created(
            new EventRegistrationResource($this->loadRegistration($registration)),
            'Inscrição registada com sucesso.'
        );
    }

    public function cancelRegistration(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('cancelRegistration', $event);

        try {
            $registration = app(CancelEventRegistration::class)($event, $request->user());
        } catch (ConflictHttpException $exception) {
            return $this->conflict($exception->getMessage());
        }

        return $this->success(
            'Inscrição cancelada com sucesso.',
            new EventRegistrationResource($this->loadRegistration($registration))
        );
    }

    public function registrations(IndexRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('viewRegistrations', $event);

        $filter = new QueryFilter(
            $request,
            filters: [
                'status' => $this->exactFilter('status'),
                'user' => $this->exactFilter('user_id'),
            ],
            sorts: [
                'status' => 'status',
                'created_at' => 'created_at',
            ],
            defaultSort: '-created_at',
        );

        $registrations = $filter->apply(
            $this->withRegistrationRelations(
                EventRegistration::query()->where('event_id', $event->getKey())
            )
        )->paginate($filter->perPage());

        return $this->paginated($registrations, EventRegistrationResource::class, $request);
    }

    public function markPresent(EventRegistration $eventRegistration): JsonResponse|EventRegistrationResource
    {
        Gate::authorize('markPresent', $eventRegistration);

        try {
            $registration = app(MarkRegistrationPresent::class)($eventRegistration);
        } catch (ConflictHttpException $exception) {
            return $this->conflict($exception->getMessage());
        }

        return new EventRegistrationResource($this->loadRegistration($registration));
    }

    public function myRegistrations(IndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', EventRegistration::class);

        $filter = new QueryFilter(
            $request,
            filters: [
                'status' => $this->exactFilter('status'),
                'event' => $this->exactFilter('event_id'),
            ],
            sorts: [
                'status' => 'status',
                'created_at' => 'created_at',
            ],
            defaultSort: '-created_at',
        );

        $registrations = $filter->apply(
            $this->withRegistrationRelations(
                EventRegistration::query()->where('user_id', $request->user()->getKey())
            )
        )->paginate($filter->perPage());

        return $this->paginated($registrations, EventRegistrationResource::class, $request);
    }

    private function eventQuery(Request $request): Builder
    {
        $query = Event::query()
            ->withCount($this->activeRegistrationsCount())
            ->with(['category', 'zone']);

        if (! $request->user()?->isModerator() && ! $request->user()?->isAdmin()) {
            $query->public();
        }

        return $query;
    }

    private function withRegistrationRelations(Builder $query): Builder
    {
        return $query->with([
            'user',
            'event' => fn (Relation $relation) => $relation->withCount($this->activeRegistrationsCount()),
        ]);
    }

    private function loadRegistration(EventRegistration $registration): EventRegistration
    {
        return $registration->loadMissing([
            'user',
            'event' => fn (Relation $relation) => $relation->withCount($this->activeRegistrationsCount()),
        ]);
    }

    private function exactFilter(string $column): callable
    {
        return fn (Builder $query, mixed $value) => $query->whereIn($column, QueryFilter::parseValues($value));
    }

    private function activeRegistrationsCount(): array
    {
        return [
            'registrations',
            'registrations as active_registrations_count' => fn (Builder $builder) => $builder->whereIn('status', [
                RegistrationStatus::INSCRITO->value,
                RegistrationStatus::PRESENTE->value,
            ]),
        ];
    }

    private function withStoredCover(Request $request, array $data): array
    {
        unset($data['cover']);

        if ($request->hasFile('cover')) {
            $data['cover_image'] = app(StoredFile::class)
                ->store($request->file('cover'), 'covers')['path'];
        }

        return $data;
    }
}
