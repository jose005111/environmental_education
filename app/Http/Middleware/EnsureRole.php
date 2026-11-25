<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        foreach ($roles as $role) {
            $roleName = RoleName::tryFrom($role);

            if ($roleName !== null && $user->hasRole($roleName)) {
                return $next($request);
            }
        }

        throw new AuthorizationException('Sem permissão para aceder a este recurso.');
    }
}
