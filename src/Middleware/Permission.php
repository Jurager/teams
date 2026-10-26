<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Permission extends Teams
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string|array $permissions, ?string $teamId = null, bool|string $options = false): mixed
    {
        $user = $request->user();

        if ($user === null || ! $user->hasTeamPermission($this->resolveTeam($request, $teamId), $this->parse($permissions), $this->isRequired($options))) {
            return $this->unauthorized();
        }

        return $next($request);
    }
}
