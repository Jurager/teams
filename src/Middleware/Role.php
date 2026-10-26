<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Role extends Teams
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string|array $roles, ?string $teamId = null, bool|string $options = false): mixed
    {
        $user = $request->user();

        if ($user === null || ! $user->hasTeamRole($this->resolveTeam($request, $teamId), $this->parse($roles), $this->isRequired($options))) {
            return $this->unauthorized();
        }

        return $next($request);
    }
}
