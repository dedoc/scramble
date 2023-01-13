<?php

namespace Dedoc\Scramble\Http\Middleware;

use Illuminate\Support\Facades\Gate;

class RestrictedDocsAccess
{
    public function handle($request, \Closure $next)
    {
        if (array_key_exists('viewApiDocs', Gate::abilities())) {
            if (Gate::allows('viewApiDocs')) {
                return $next($request);
            }
            
            abort(403);
        }
        
        if (! app()->environment('production')) {
            return $next($request);
        }

        abort(403);
    }
}
