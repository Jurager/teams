<?php

namespace Jurager\Teams\Middleware;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Database\Eloquent\Model;

class Teams
{
	/**
	 * Check if the request has authorization to continue.
	 *
	 * @param Request $request
	 * @param $method
	 * @param $params
	 * @param string|null $team_id
	 * @param $models
	 * @param boolean $require
	 * @return boolean
	 */
	protected function authorization(Request $request, $method, $params, ?string $team_id, $models, bool $require = false): bool
	{
		// Determinate the method for checking the role or permissions
		//
		$method = match ($method) {
			'roles'       => 'hasTeamRole',
			'permissions' => 'hasTeamPermission',
			'ability'     => 'hasTeamAbility'
		};

		if (!is_array($params)) {
			$params = explode('|', $params);
		}

		// Foreign key for team_id field
		//
		$foreign = Config::get('teams.foreign_keys.team_id');

		// If team id not directly passed get the id by request or route param
		//
		$foreign_id = $team_id ?? ($request->get($foreign) ?? $request->route($foreign));

		// Get the team model
		//
		$team = (\Jurager\Teams\Teams::teamModel())::where('id', $foreign_id)->firstOrFail();

		// Check the ability
		//
		if($method === 'hasTeamAbility') {

			// Get the models
			//
			$args =  $this->getGateArguments($request, $models);

			// Checking abilities to specific model object
			//
			if(isset($args[1])) {

				// Get the data from models
				//
				$entity = (($args[0] === 'App\Models\Team') ? $args[0]::where('id', $args[1])->first() : $args[0]::where('id', $args[1]))->first();

				// Check the entity
                // Check the ability to entity for current user
                //
                if($entity && $request->user()->hasTeamAbility($team, $params, $entity)) {
                    return true;
                }
			}


			return false;
		}

		// Check the permissions
		//
		return !Auth::guest() && Auth::user()?->$method($team, $params, $require);
	}

	/**
	 * The request is unauthorized, so it handles the aborting/redirecting.
	 *
	 * @return RedirectResponse
	 */
	protected function unauthorized(): RedirectResponse
	{
        // Method to be called in the middleware return
        //
		$handling = Config::get('teams.middleware.handling');

        // Handlers for the unauthorized method
        //
		$handler  = Config::get('teams.middleware.handlers.'.$handling);

        // Abort handler simply returns unauthorized message
        //
		if ($handling === 'abort') {
			return App::abort($handler['code'], $handler['message'] ?? 'User does not have any of the necessary access rights.');
		}

        // Otherwise declare redirect method
        //
		$redirect = Redirect::to($handler['url']);

        // Handler message is defined in configuration
        //
		if (!empty($handler['message']['content'])) {

            // Append session flash message to redirect
            //
			$redirect->with($handler['message']['key'], $handler['message']['content']);
		}

        // Perform redirect to handler defined route
        //
		return $redirect;
	}

	/**
	 * Get the arguments parameters for the gate.
     *
	 * @param $request
	 * @param $models
	 * @return array
	 */
	protected function getGateArguments($request, $models): array
	{
        // Gate model not defined, return empty array
        //
		if (is_null($models)) {
			return [];
		}

        // Map through all models and detect actual model instance
        //
        return array_map(function($model) use ($request) {
            return $model instanceof Model ? $model : $this->getModel($request, $model);
        }, $models);

	}


	/**
	 * Get the model to authorize.
     *
	 * @param $request
	 * @param $model
	 * @return string
	 */
	protected function getModel($request, $model): string
	{
		return str_contains($model, '\\') ? trim($model) : $request->route($model, $model);
	}
}