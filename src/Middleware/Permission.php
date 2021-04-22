<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Permission extends Teams
{
	/**
	 * Handle incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  Closure $next
	 * @param  string  $permissions
	 * @param  string|null  $team
	 * @param  string|null  $options
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next, $permissions, $team = null, $options = '')
	{
		if (!$this->authorization($request, 'permissions', $permissions, $team, $options)) {
			return $this->unauthorized();
		}

		return $next($request);
	}
}