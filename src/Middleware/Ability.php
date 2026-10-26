<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Ability extends Teams
{
    /**
     * Handle an incoming request.
     *
     * Passes when the user has any of the given abilities ("edit|delete") on the entity.
     */
    public function handle(Request $request, Closure $next, string $abilities, ...$models): mixed
    {
        $user = $request->user();

        if ($user === null) {
            return $this->unauthorized();
        }

        $team = $this->resolveTeam($request, null);
        $entity = $this->resolveEntity($request, $models);

        if ($entity === null || ! $this->belongsToTeam($entity, $team)) {
            return $this->unauthorized();
        }

        foreach ($this->parse($abilities) as $ability) {
            if ($user->hasTeamAbility($team, $ability, $entity)) {
                return $next($request);
            }
        }

        return $this->unauthorized();
    }
}
