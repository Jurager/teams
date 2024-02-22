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
     * @param Closure $next
     * @param string|array $roles
     * @param string|null $team_id
     * @param bool $options
     * @return mixed
     */
	public function handle(Request $request, Closure $next, string|array $roles, string|null $team_id = null, bool $options = false): mixed
	{
		if (!$this->authorization($request, 'roles', $roles, $team_id, [], $options)) {
			return $this->unauthorized();
		}

		return $next($request);
	}
}