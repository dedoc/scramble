<?php

namespace Dedoc\Scramble\Http\Middleware;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class RestrictedDocsAccess
{
    public function handle($request, \Closure $next)
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        $user = Auth::guard(config('scramble.guard'))->user();
        if (Gate::forUser($user)->allows('viewApiDocs')) {
            return $next($request);
        }

        abort(403);
    }
}
