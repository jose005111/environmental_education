<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contents\PublishContent;
use App\Actions\Contents\StoreContent;
use App\Actions\Contents\UpdateContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequest;
use App\Http\Requests\Api\V1\PublishContentRequest;
use App\Http\Requests\Api\V1\StoreContentRequest;
use App\Http\Requests\Api\V1\UpdateContentRequest;
use App\Http\Resources\ContentResource;
use App\Http\Resources\ContentSummaryResource;
use App\Models\Content;
use App\Support\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;

class ContentController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Content::class);

        $filter = new QueryFilter(
            $request,
            filters: [
                'category' => fn (Builder $query, mixed $value) => $query->whereIn('category_id', QueryFilter::parseValues($value)),
                'type' => fn (Builder $query, mixed $value) => $query->whereIn('type', QueryFilter::parseValues($value)),
                'status' => fn (Builder $query, mixed $value) => $query->whereIn('status', QueryFilter::parseValues($value)),
            ],
            sorts: [
                'title' => 'title',
                'published_at' => 'published_at',
                'views_count' => 'views_count',
                'created_at' => 'created_at',
            ],
            search: ['title', 'summary', 'body'],
            defaultSort: '-published_at',
        );

        $query = Content::query()->with('category');

        if (! $request->user()?->isModerator() && ! $request->user()?->isAdmin()) {
            $query->published();
        }

        $contents = $filter->apply($query)
            ->paginate($filter->perPage());

        return $this->paginated($contents, ContentSummaryResource::class, $request);
    }

    public function show(Request $request, string $slug): ContentResource
    {
        $query = Content::query()->where('slug', $slug)->with(['category', 'author']);

        $canManage = $request->user()?->isModerator() || $request->user()?->isAdmin();
        if (! $canManage) {
            $query->published();
        }

        $content = $query->firstOrFail();

        Gate::authorize('view', $content);

        if (! $canManage) {
            $content->increment('views_count');
        }

        return new ContentResource($content->refresh());
    }

    public function store(StoreContentRequest $request, StoreContent $action): JsonResponse
    {
        Gate::authorize('create', Content::class);

        $content = $action->execute($request->validated(), $request->user()->id);

        return $this->created(
            new ContentResource($content->load(['category', 'author'])),
            'Conteúdo criado com sucesso.',
        );
    }

    public function update(UpdateContentRequest $request, Content $content, UpdateContent $action): JsonResponse
    {
        Gate::authorize('update', $content);

        $content = $action->execute($content, $request->validated());

        return $this->success(
            'Conteúdo actualizado com sucesso.',
            new ContentResource($content->load(['category', 'author'])),
        );
    }

    public function publish(PublishContentRequest $request, Content $content, PublishContent $action): JsonResponse
    {
        Gate::authorize('publish', $content);

        $content = $action->execute($content, $request->validated()['status']);

        return $this->success(
            'Estado do conteúdo actualizado com sucesso.',
            new ContentResource($content->load(['category', 'author'])),
        );
    }

    public function destroy(Content $content): HttpResponse
    {
        Gate::authorize('delete', $content);

        $content->delete();

        return $this->noContent();
    }
}
