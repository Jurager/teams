<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Ability extends Teams
{
	/**
	 * Handle incoming request.
	 *
	 * @param Request $request
	 * @param Closure $next
	 * @param string $ability
	 * @param array ...$models
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next, string $ability, ...$models): mixed
	{
		if (!$this->authorization($request,'ability', $ability, null, $models)) {
			return $this->unauthorized();
		}

		return $next($request);
	}
}