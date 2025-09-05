<?php
declare(strict_types=1);

namespace Dedoc\Scramble\Http\Middleware;

use Illuminate\Support\Facades\Gate;

/**
 *
 */
class RestrictedDocsAccess
{
    public function handle($request, \Closure $next)
    {
        if (app()->environment(config('scramble.allowed_environments'))) {
            return $next($request);
        }

        if (Gate::allows('viewApiDocs')) {
            return $next($request);
        }

        abort(403);
    }
}
