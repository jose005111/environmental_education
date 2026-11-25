<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequest;
use App\Http\Requests\Api\V1\StoreZoneRequest;
use App\Http\Requests\Api\V1\UpdateZoneRequest;
use App\Http\Resources\ZoneResource;
use App\Http\Resources\ZoneSummaryResource;
use App\Models\Zone;
use App\Support\QueryFilter;
use App\Support\ReferenceDataCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;

class ZoneController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $filter = new QueryFilter(
            $request,
            filters: [
                'name' => ['column' => 'name', 'operator' => 'like'],
            ],
            sorts: [
                'name' => 'name',
                'created_at' => 'created_at',
            ],
            search: ['name', 'description'],
            defaultSort: 'name',
        );

        $zones = ReferenceDataCache::remember(
            'zones',
            $request,
            fn () => $filter->apply(Zone::query())->paginate($filter->perPage()),
        );

        return $this->paginated($zones, ZoneSummaryResource::class, $request);
    }

    public function store(StoreZoneRequest $request): JsonResponse
    {
        $zone = Zone::create($request->validated());

        return $this->created(new ZoneResource($zone), 'Zona criada com sucesso.');
    }

    public function update(UpdateZoneRequest $request, Zone $zone): JsonResponse
    {
        $zone->fill($request->validated())->save();

        return $this->success('Zona actualizada com sucesso.', new ZoneResource($zone));
    }

    public function destroy(Zone $zone): HttpResponse|JsonResponse
    {
        foreach (['users', 'reports', 'events'] as $relation) {
            if ($zone->{$relation}()->exists()) {
                return $this->conflict('Não é possível apagar uma zona com registos associados.');
            }
        }

        $zone->delete();

        return $this->noContent();
    }
}
