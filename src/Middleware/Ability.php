<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Ability extends Teams
{
	/**
	 * Handle incoming request.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param Closure $next
	 * @param string $ability
	 * @param string|null $team_id
	 * @param mixed ...$models
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next, string $ability, string $team_id = null, ...$models)
	{
		if (!$this->authorization($request,'can', $ability, $team_id, $models)) {
			return $this->unauthorized();
		}

		return $next($request);
	}
}