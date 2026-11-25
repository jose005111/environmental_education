<?php

namespace App\Http\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

trait ApiResponses
{
    protected function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        $body = ['message' => $message];

        if ($data !== null) {
            $body['data'] = $data;
        }

        return response()->json($body, $status);
    }

    protected function created(mixed $data, string $message = 'Registo criado com sucesso.'): JsonResponse
    {
        return $this->success($message, $data, 201);
    }

    protected function noContent(): HttpResponse
    {
        return response()->noContent();
    }

    protected function conflict(string $message = 'Conflito com o estado actual do recurso.'): JsonResponse
    {
        return response()->json([
            'status' => 409,
            'code' => 'conflict',
            'message' => $message,
        ], 409);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $resourceClass, Request $request): JsonResponse
    {
        return response()->json([
            'data' => $resourceClass::collection($paginator->items()),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
