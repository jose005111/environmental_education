<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('sanctum');
        $user = $guard->user();

        if ($user instanceof User && ! $user->is_active) {
            $user->tokens()->delete();
            $request->setUserResolver(fn () => null);

            return response()->json([
                'status' => 403,
                'code' => 'forbidden',
                'message' => 'Esta conta está desactivada.',
            ], 403);
        }

        return $next($request);
    }
}
