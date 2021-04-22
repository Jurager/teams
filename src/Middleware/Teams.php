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
	/**
	 * Check if the request has authorization to continue.
	 *
	 * @param  string $type
	 * @param  string $rolesPermissions
	 * @param  string|null $team
	 * @param  string|null $options
	 * @return boolean
	 */
	protected function authorization(Request $request, $method, $params, $options)
	{
		$method  = $method == 'roles' ? 'hasTeamRole' : 'hasTeamPermission';

		// Foreign key for team_id field
		//
		$foreign = Config::get('teams.foreign_keys.team_id');

		// Get the team model by requested foreign key
		//
		$team    = (\Jurager\Teams\Teams::teamModel())::where('id', $request->get($foreign))->first();

		if(!$team) {
			return false;
		}

		return !Auth::guest() && Auth::user()->$method($team, $params);
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
			$defaultMessage = 'User does not have any of the necessary access rights.';

			return App::abort($handler['code'], $handler['message'] ?? $defaultMessage);
		}

		$redirect = Redirect::to($handler['url']);

		if (!empty($handler['message']['content'])) {
			$redirect->with($handler['message']['key'], $handler['message']['content']);
		}

		return $redirect;
	}
}