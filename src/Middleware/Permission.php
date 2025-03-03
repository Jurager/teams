<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Permission extends Teams
{
    /**
     * Handle incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|array $permissions
     * @param string|null $teamId
     * @param bool $options
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string|array $permissions, ?string $teamId = null, bool $options = false): mixed
    {
        if (! $this->authorization($request, 'permission', $permissions, $teamId, [], $options)) {
            return $this->unauthorized();
        }

        return $next($request);
    }
}
