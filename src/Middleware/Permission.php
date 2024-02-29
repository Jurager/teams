<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Permission extends Teams
{
    /**
     * Handle incoming request.
     */
    public function handle(Request $request, Closure $next, string|array $permissions, ?string $team_id = null, bool $options = false): mixed
    {
        if (! $this->authorization($request, 'permissions', $permissions, $team_id, [], $options)) {
            return $this->unauthorized();
        }

        return $next($request);
    }
}
