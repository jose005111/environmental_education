<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequest;
use App\Http\Requests\Api\V1\StoreCategoryRequest;
use App\Http\Requests\Api\V1\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CategorySummaryResource;
use App\Models\Category;
use App\Support\QueryFilter;
use App\Support\ReferenceDataCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;

class CategoryController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $filter = new QueryFilter(
            $request,
            filters: [
                'name' => ['column' => 'name', 'operator' => 'like'],
                'slug' => ['column' => 'slug', 'operator' => '='],
                'category' => ['column' => 'id', 'operator' => '='],
            ],
            sorts: [
                'name' => 'name',
                'slug' => 'slug',
                'created_at' => 'created_at',
            ],
            search: ['name', 'description'],
            defaultSort: 'name',
        );

        $categories = ReferenceDataCache::remember(
            'categories',
            $request,
            fn () => $filter->apply(Category::query())->paginate($filter->perPage()),
        );

        return $this->paginated($categories, CategorySummaryResource::class, $request);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category->load('contents')->loadCount('contents'));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return $this->created(new CategoryResource($category), 'Categoria criada com sucesso.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->fill($request->validated())->save();

        return $this->success('Categoria actualizada com sucesso.', new CategoryResource($category));
    }

    public function destroy(Category $category): HttpResponse|JsonResponse
    {
        foreach (['contents', 'quizzes', 'activities', 'reports', 'events'] as $relation) {
            if ($category->{$relation}()->exists()) {
                return $this->conflict('Não é possível apagar uma categoria com registos associados.');
            }
        }

        $category->delete();

        return $this->noContent();
    }
}
