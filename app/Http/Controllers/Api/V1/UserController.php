<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use App\Support\QueryFilter;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $filter = new QueryFilter(
            $request,
            filters: [
                'role' => fn ($query, $value) => $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('name', $value)),
                'zone' => ['column' => 'zone_id', 'operator' => '='],
                'is_active' => ['column' => 'is_active', 'operator' => '='],
            ],
            sorts: [
                'name' => 'name',
                'email' => 'email',
                'created_at' => 'created_at',
            ],
            search: ['name', 'email'],
        );

        $users = $filter->apply(User::query()->with('role'))->paginate($filter->perPage());

        return $this->paginated($users, UserSummaryResource::class, $request);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->fill($request->validated())->save();

        return $this->success('Utilizador actualizado com sucesso.', new UserResource($user->load('role', 'zone')));
    }
}
