<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reports\AssignReport;
use App\Actions\Reports\ChangeReportStatus;
use App\Actions\Reports\CreateReport;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignReportRequest;
use App\Http\Requests\Api\V1\IndexRequest;
use App\Http\Requests\Api\V1\StoreReportRequest;
use App\Http\Requests\Api\V1\UpdateReportStatusRequest;
use App\Http\Resources\ReportPublicResource;
use App\Http\Resources\ReportResource;
use App\Http\Resources\ReportSummaryResource;
use App\Models\Report;
use App\Models\User;
use App\Support\QueryFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function publicIndex(IndexRequest $request): JsonResponse
    {
        Gate::authorize('viewPublic', Report::class);

        $filter = new QueryFilter(
            $request,
            filters: [
                'zone' => $this->whereFilter('zone_id'),
                'zone_id' => $this->whereFilter('zone_id'),
                'category' => $this->whereFilter('category_id'),
                'category_id' => $this->whereFilter('category_id'),
                'status' => $this->whereFilter('status'),
            ],
            sorts: [
                'title' => 'title',
                'status' => 'status',
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
                'resolved_at' => 'resolved_at',
            ],
            search: ['title', 'description', 'location_note'],
        );

        $reports = $filter->apply(
            Report::query()->public()->with(['zone', 'category', 'photos']),
        )->paginate($filter->perPage());

        return $this->paginated($reports, ReportPublicResource::class, $request);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        /** @var User $author */
        $author = $request->user();

        $photos = $request->file('photos');
        $photos = is_array($photos) ? array_values($photos) : ($photos === null ? [] : [$photos]);

        $report = app(CreateReport::class)->execute($request->validated(), $author, $photos);

        return $this->created(
            new ReportResource($report->load($this->relations())),
            'Reporte criado com sucesso.',
        );
    }

    public function mine(IndexRequest $request): JsonResponse
    {
        $filter = new QueryFilter(
            $request,
            filters: [
                'zone' => $this->whereFilter('zone_id'),
                'zone_id' => $this->whereFilter('zone_id'),
                'category' => $this->whereFilter('category_id'),
                'category_id' => $this->whereFilter('category_id'),
                'status' => $this->whereFilter('status'),
            ],
            sorts: [
                'title' => 'title',
                'status' => 'status',
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
            ],
            search: ['title', 'description', 'location_note'],
        );

        $reports = $filter->apply(
            Report::query()
                ->where('user_id', $request->user()->getKey())
                ->with($this->relations()),
        )->paginate($filter->perPage());

        return $this->paginated($reports, ReportResource::class, $request);
    }

    public function index(IndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Report::class);

        $filter = new QueryFilter(
            $request,
            filters: [
                'zone' => $this->whereFilter('zone_id'),
                'zone_id' => $this->whereFilter('zone_id'),
                'status' => $this->whereFilter('status'),
                'assigned' => $this->whereFilter('assigned_to'),
                'assigned_to' => $this->whereFilter('assigned_to'),
                'category' => $this->whereFilter('category_id'),
                'category_id' => $this->whereFilter('category_id'),
            ],
            sorts: [
                'title' => 'title',
                'status' => 'status',
                'created_at' => 'created_at',
                'updated_at' => 'updated_at',
                'resolved_at' => 'resolved_at',
            ],
            search: ['title', 'description', 'location_note'],
        );

        $reports = $filter->apply(Report::query()->with($this->relations()))
            ->paginate($filter->perPage());

        return $this->paginated($reports, ReportSummaryResource::class, $request);
    }

    public function show(Request $request, Report $report): JsonResponse
    {
        if ($request->user() === null && ! $this->isPublic($report)) {
            abort(401);
        }

        Gate::authorize('view', $report);

        return $this->success(
            'Reporte encontrado.',
            new ReportResource($report->load($this->relations())),
        );
    }

    public function assign(AssignReportRequest $request, Report $report): JsonResponse
    {
        $assignedTo = $request->validated()['assigned_to'] ?? null;

        $report = app(AssignReport::class)->execute($report, $assignedTo);

        return $this->success(
            'Responsável do reporte actualizado com sucesso.',
            new ReportResource($report->load($this->relations())),
        );
    }

    public function changeStatus(UpdateReportStatusRequest $request, Report $report): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validated();

        $report = app(ChangeReportStatus::class)->execute(
            $report,
            $actor,
            ReportStatus::from($data['status']),
            $data['note'] ?? null,
            array_key_exists('is_public', $data)
                ? filter_var($data['is_public'], FILTER_VALIDATE_BOOLEAN)
                : null,
        );

        return $this->success(
            'Estado do reporte actualizado com sucesso.',
            new ReportResource($report->load($this->relations())),
        );
    }

    private function relations(): array
    {
        return ['user', 'zone', 'category', 'assignee', 'photos', 'updates.user'];
    }

    private function whereFilter(string $column): callable
    {
        return fn ($query, mixed $value) => $query->whereIn($column, QueryFilter::parseValues($value));
    }

    private function isPublic(Report $report): bool
    {
        return $report->status === ReportStatus::RESOLVIDO && $report->is_public;
    }
}
