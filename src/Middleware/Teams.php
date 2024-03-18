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
	 * @param $models
	 * @param boolean $require
	 * @return boolean
	 */
	protected function authorization(Request $request, $method, $params, ?string $team_id, $models, bool $require = false)
	{
		// Determinate the method for checking the role or permissions
		//
		$method = match ($method) {
			'roles'       => 'hasTeamRole',
			'permissions' => 'hasTeamPermission',
			'ability'     => 'hasTeamAbility'
		};

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
		$team = (\Jurager\Teams\Teams::teamModel())::where('id', $foreign_id)->withoutRelations()->firstOrFail();

		// Check the ability
		//
		if($method == 'hasTeamAbility') {

			// Get the models
			//
			$args =  $this->getGateArguments($request, $models);

			// Checking abilities to specific model object
			//
			if(isset($args[1])) {

				// Get the data from models
				//
				$entity   = (($args[0] == 'App\Models\Team') ? $args[0]::where('id', $args[1])->first() : $args[0]::where('id', $args[1]))->first();

				// Check the entity
				//
				if($entity) {

					// Check the ability to entity for current user
					//
					if ($request->user()->hasTeamAbility($team, $params, $entity)) {
						return true;
					}
				}
			}


			return false;
		}

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

	/**
	 * Get the arguments parameter for the gate.
	 * @param $request
	 * @param $models
	 * @return array
	 */
	protected function getGateArguments($request, $models)
	{
		if (is_null($models)) {
			return [];
		}

		return collect($models)->map(function ($model) use ($request) {
			return $model instanceof Model ? $model : $this->getModel($request, $model);
		})->all();
	}


	/**
	 * Get the model to authorize.
	 * @param $request
	 * @param $model
	 * @return string
	 */
	protected function getModel($request, $model)
	{
		return $this->isClassName($model) ? trim($model) : $request->route($model, $model);
	}

	/**
	 * Checks if the given string looks like a fully qualified class name.
	 *
	 * @param  string  $value
	 * @return bool
	 */
	protected function isClassName($value)
	{
		return strpos($value, '\\') !== false;
	}
}
