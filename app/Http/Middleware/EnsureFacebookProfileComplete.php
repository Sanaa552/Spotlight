<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFacebookProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->needsFacebookProfileCompletion()) {
            return redirect()->route('facebook.profile.edit');
        }

        return $next($request);
    }
}
