<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Role extends Teams
{
	/**
	 * Handle incoming request.
	 *
	 * @param Request $request
	 * @param  Closure $next
	 * @param  string $roles
	 * @param string|null $team_id
	 * @param string|null $options
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next, string $roles, string $team_id = null, ?string $options = ''): mixed
	{
		if (!$this->authorization($request,'roles', $roles, $team_id, [], $options)) {
			return $this->unauthorized();
		}

		return $next($request);
	}
}