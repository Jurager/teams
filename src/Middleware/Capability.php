<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Capability extends Teams
{

    /**
     * Handle incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|array $capabilities
     * @param string|null $teamId
     * @param bool $options
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string|array $capabilities, ?string $teamId = null, bool $options = false): mixed
    {
        if (! $this->authorization($request, 'capability', $capabilities, $teamId, [], $options)) {
            return $this->unauthorized();
        }

        return $next($request);
    }
}