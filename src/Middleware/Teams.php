<?php

namespace Jurager\Teams\Middleware;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class Teams
{
    /**
     * Check if the request has authorization to continue.
     */
    protected function authorization(Request $request, string $method, string|array $params, ?string $team_id, ?array $models, bool $require = false): bool
    {
        // Mapping of method names
        $method_types = [
            'roles' => 'hasTeamRole',
            'permissions' => 'hasTeamPermission',
            'ability' => 'hasTeamAbility',
        ];

        // Determine the action for checking the role or permissions
        $action = $method_types[$method] ?? null;

        // Ensure method is valid
        if ($action === null) {
            throw new InvalidArgumentException('Invalid method');
        }

        // Convert params to array if it's not already
        $params = is_array($params) ? $params : explode('|', $params);

        // Foreign key for team_id field
        $foreign = config('teams.foreign_keys.team_id', 'team_id');

        // If team id not directly passed get the id by request or route param
        $foreign_id = $team_id ?? ($request->input($foreign) ?? $request->route($foreign));

        // Get the team model
        $team = (\Jurager\Teams\Teams::$teamModel)::where('id', $foreign_id)->setEagerLoads([])->firstOrFail();

        // Check the ability
        if ($action === 'hasTeamAbility') {

            // Get the models for ability check
            [$entity_class, $entity_id] = $this->getGateArguments($request, $models);

            // Ensure entity id is provided
            if ($entity_id === null) {
                return false;
            }

            // Fetch the entity model or return false if not found
            $entity = $entity_class::findOrFail($entity_id);

            // Check the ability for the entity for the current user
            return $entity && $request->user()->hasTeamAbility($team, $params, $entity);
        }

        // Check the permissions
        return ! Auth::guest() && Auth::user()?->$action($team, $params, $require);
    }

    /**
     * The request is unauthorized, so it handles the aborting/redirecting.
     */
    protected function unauthorized(): RedirectResponse
    {
        // Method to be called in the middleware return
        $handling = config('teams.middleware.handling');

        // Handlers for the unauthorized method
        $handler = config('teams.middleware.handlers.'.$handling);

        // Abort handler simply returns unauthorized message
        if ($handling === 'abort') {
            abort($handler['code'], $handler['message']);
        }

        // Prepare redirect response
        $redirect = redirect()->to($handler['url']);

        // If flash message key is provided, use it for session message
        if (! empty($handler['message']['key'])) {
            $redirect->with($handler['message']['key'], $handler['message']['content']);
        }

        // Perform redirect or abort based on handling method
        return $redirect;
    }

    /**
     * Get the arguments parameters for the gate.
     */
    protected function getGateArguments(Request $request, ?array $models): array
    {
        // Gate model not defined, return empty array
        if ($models === null) {
            return [];
        }

        // Filter out invalid model instances and fetch actual model instances
        return array_map(function ($model) use ($request) {
            return $model instanceof Model ? $model : $this->getModel($request, $model);
        }, $models);
    }

    /**
     * Get the model to authorize.
     */
    protected function getModel(Request $request, $model): string
    {
        if (str_contains($model, '\\')) {
            return trim($model);
        }

        return $request->route($model, $model);
    }
}
