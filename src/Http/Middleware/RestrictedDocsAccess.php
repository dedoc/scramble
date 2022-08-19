<?php

namespace Dedoc\Scramble\Http\Middleware;

use Illuminate\Support\Facades\Gate;

class RestrictedDocsAccess
{
    public function handle($request, \Closure $next)
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        if (!! $request->user() && array_key_exists('viewApiDocs', Gate::abilities()) && Gate::check('viewApiDocs', [$request->user()])) {
            return $next($request);
        }

        abort(403);
    }
}
