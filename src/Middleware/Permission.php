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
	 * @param  Closure $next
	 * @param string|array $permissions
	 * @param string|null $team_id
	 * @param string|null $options
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next, string|array $permissions, string $team_id = null, ?string $options = ''): mixed
	{
		if (!$this->authorization($request, 'permissions', $permissions, $team_id, [], $options)) {
			return $this->unauthorized();
		}

		return $next($request);
	}
}