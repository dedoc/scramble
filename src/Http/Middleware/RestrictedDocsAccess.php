<?php

namespace Dedoc\Scramble\Http\Middleware;

use Illuminate\Support\Facades\Gate;

class RestrictedDocsAccess
{
    public function handle($request, \Closure $next)
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        if (array_key_exists('viewApiDocs', Gate::abilities()) && Gate::allows('viewApiDocs')) {
            return $next($request);
        }

        abort(403);
    }
}
