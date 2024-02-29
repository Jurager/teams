<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Ability extends Teams
{
    /**
     * Handle incoming request.
     *
     * @param  array  ...$models
     */
    public function handle(Request $request, Closure $next, string $ability, ...$models): mixed
    {
        if (! $this->authorization($request, 'ability', $ability, null, $models)) {
            return $this->unauthorized();
        }

        return $next($request);
    }
}
