<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Usage dans les routes: ->middleware('role:moderateur,administrateur')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $allowed = array_map(fn (string $r) => Role::from($r), $roles);

        if (! in_array($user->role, $allowed, true)) {
            abort(403, "Accès réservé aux rôles : " . implode(', ', $roles));
        }

        if ($user->is_blocked) {
            abort(403, "Votre compte a été bloqué.");
        }

        return $next($request);
    }
}