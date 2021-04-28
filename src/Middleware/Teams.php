<?php

namespace Jurager\Teams\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;

class Teams
{
	const DELIMITER = '|';

	/**
	 * Check if the request has authorization to continue.
	 *
	 * @param \Illuminate\Http\Request $request
	 * @param $method
	 * @param $params
	 * @param string|null $team_id
	 * @param boolean $require
	 * @return boolean
	 */
	protected function authorization(Request $request, $method, $params, $team_id, $require = false)
	{
		// Determinate the method for checking the role or permissions
		//
		$method  = $method == 'roles' ? 'hasTeamRole' : 'hasTeamPermission';

		if (!is_array($params)) {
			$params = explode(self::DELIMITER, $params);
		}

		// Foreign key for team_id field
		//
		$foreign = Config::get('teams.foreign_keys.team_id');

		// If team id not directly passed get the id by request or route param
		//
		$foreign_id = $team_id ?? ($request->get($foreign) ?? $request->route($foreign));

		// Get the team model
		//
		$team = (\Jurager\Teams\Teams::teamModel())::where('id', $request->get($foreign_id))->firstOrFail();

		// Check the permissions
		//
		return !Auth::guest() && Auth::user()->$method($team, $params, $require);
	}

	/**
	 * The request is unauthorized, so it handles the aborting/redirecting.
	 *
	 * @return \Illuminate\Http\Response
	 */
	protected function unauthorized()
	{
		$handling = Config::get('teams.middleware.handling');
		$handler  = Config::get('teams.middleware.handlers.'.$handling);

		if ($handling == 'abort') {
			$message = 'User does not have any of the necessary access rights.';

			return App::abort($handler['code'], $handler['message'] ?? $message);
		}

		$redirect = Redirect::to($handler['url']);

		if (!empty($handler['message']['content'])) {
			$redirect->with($handler['message']['key'], $handler['message']['content']);
		}

		return $redirect;
	}
}