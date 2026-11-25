<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentType;
use App\Enums\EventStatus;
use App\Enums\ParticipationStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSiteRequest;
use App\Http\Resources\ReportPublicResource;
use App\Models\Activity;
use App\Models\ActivityParticipation;
use App\Models\Category;
use App\Models\Content;
use App\Models\Event;
use App\Models\Report;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SiteController extends Controller
{
    public function home(Request $request): JsonResponse
    {
        $site = SiteSetting::query()->find(1);
        $featuredIds = $site->featured_content_ids ?? [];
        $featured = Content::query()->published()->with('category')
            ->whereIn('id', $featuredIds)->get()->sortBy(fn (Content $content) => array_search($content->id, $featuredIds, true))
            ->values()->map(fn (Content $content) => $this->contentCard($content));

        $events = Event::query()->published()->where('starts_at', '>=', now())
            ->with(['category', 'zone'])->withCount([
                'registrations as active_registrations_count' => fn ($query) => $query->whereIn('status', ['inscrito', 'presente']),
            ])->orderBy('starts_at')->limit(6)->get();

        $reports = Report::query()->public()->with(['zone', 'category', 'photos'])
            ->orderByDesc('resolved_at')->limit(6)->get();

        return response()->json([
            'hero' => $site->hero ?? $this->emptyHero(),
            'stats' => [
                'reports_resolved' => Report::query()->public()->count(),
                'activities_completed' => ActivityParticipation::query()->where('status', ParticipationStatus::VALIDADO)->count(),
                'events_held' => Event::query()->where('status', EventStatus::CONCLUIDO)->count(),
                'residents' => User::query()->whereHas('role', fn ($query) => $query->where('name', RoleName::RESIDENTE->value))->where('is_active', true)->count(),
            ],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'slug', 'description']),
            'featured_contents' => $featured,
            'upcoming_events' => $events->map(fn (Event $event) => [
                'slug' => $event->slug,
                'title' => $event->title,
                'location' => $event->location,
                'starts_at' => $event->starts_at?->toJSON(),
                'cover_url' => $this->fileUrl($event->cover_image),
                'capacity' => $event->capacity,
                'remaining_places' => $event->capacity === null ? null : max(0, $event->capacity - (int) $event->getAttribute('active_registrations_count')),
            ]),
            'open_activities' => Activity::query()->open()->with('category')->orderBy('ends_on')->limit(6)->get()
                ->map(fn (Activity $activity) => [
                    'slug' => $activity->slug,
                    'title' => $activity->title,
                    'summary' => $activity->summary,
                    'ends_on' => $activity->ends_on?->toDateString(),
                    'category' => $activity->category?->only(['id', 'name', 'slug']),
                ]),
            'resolved_reports' => ReportPublicResource::collection($reports)->resolve($request),
            'tips' => Content::query()->published()->where('type', ContentType::DICA)->with('category')
                ->orderByDesc('published_at')->limit(6)->get()->map(fn (Content $content) => $this->contentCard($content)),
            'how_it_works' => $site->how_it_works ?? [],
            'settings' => $site->settings ?? $this->emptySettings(),
        ]);
    }

    public function show(): JsonResponse
    {
        $site = SiteSetting::query()->find(1);

        return response()->json([
            'hero' => $site->hero ?? $this->emptyHero(),
            'how_it_works' => $site->how_it_works ?? [],
            'settings' => $site->settings ?? $this->emptySettings(),
            'featured_content_ids' => $site->featured_content_ids ?? [],
        ]);
    }

    public function update(UpdateSiteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $published = Content::query()->published()->whereIn('id', $data['featured_content_ids'])->count();
        if ($published !== count($data['featured_content_ids'])) {
            throw ValidationException::withMessages(['featured_content_ids' => 'Escolha apenas conteúdos publicados.']);
        }

        DB::transaction(function () use ($data): void {
            $site = SiteSetting::query()->lockForUpdate()->find(1) ?? new SiteSetting(['id' => 1]);
            $site->forceFill(['id' => 1, ...$data])->save();
        });

        return $this->show();
    }

    private function contentCard(Content $content): array
    {
        return [
            'slug' => $content->slug,
            'title' => $content->title,
            'summary' => $content->summary,
            'type' => $content->getRawOriginal('type'),
            'cover_url' => $this->fileUrl($content->cover_image),
            'category' => $content->category?->only(['id', 'name', 'slug']),
        ];
    }

    private function fileUrl(?string $path): ?string
    {
        return $path ? Storage::disk(config('uploads.disk', 'public'))->url($path) : null;
    }

    private function emptyHero(): array
    {
        return [
            'title' => '', 'subtitle' => '', 'image_url' => null, 'image_alt' => null, 'video_url' => null,
            'primary_cta' => ['label' => '', 'href' => ''],
            'secondary_cta' => ['label' => '', 'href' => ''],
        ];
    }

    private function emptySettings(): array
    {
        return ['site_name' => '', 'contact_email' => null, 'social' => [], 'footer_text' => null];
    }
}
