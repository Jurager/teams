<?php

namespace Jurager\Teams\Middleware;

use Closure;
use Illuminate\Http\Request;

class Role extends Teams
{
	/**
	 * Handle incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  Closure $next
	 * @param  string  $roles
	 * @param  string|null  $team
	 * @param  string|null  $options
	 * @return mixed
	 */
	public function handle(Request $request, Closure $next, $roles, $team = null, $options = '')
	{
		if (!$this->authorization($request,'roles', $roles, $team, $options)) {
			return $this->unauthorized();
		}

		return $next($request);
	}
}