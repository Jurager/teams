<?php

namespace Jurager\Teams\Middleware;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams as TeamFacade;
use InvalidArgumentException;

class Teams
{
    /**
     * Check if the request has authorization to continue.
     *
     * @param Request $request
     * @param string $method
     * @param string|array $params
     * @param string|null $teamId
     * @param array|null $models
     * @param bool $require
     * @return bool
     */
    protected function authorization(Request $request, string $method, string|array $params, ?string $teamId, ?array $models, bool $require = false): bool
    {
        // Mapping of method names
        $methodTypes = [
            'roles' => 'hasTeamRole',
            'permission' => 'hasTeamPermission',
            'ability' => 'hasTeamAbility',
        ];

        // Determine the action for checking the role or permissions
        $action = $methodTypes[$method] ?? null;

        // Ensure method is valid
        if ($action === null) {
            throw new InvalidArgumentException('Invalid method');
        }

        // Convert params to array if it's not already
        $params = is_array($params) ? $params : explode('|', $params);

        // Foreign key for team_id field
        $foreignKey = Config::get('teams.foreign_keys.team_id', 'team_id');

        // If team id not directly passed, get the id from request or route param
        $foreignId = $teamId ?? $request->input($foreignKey) ?? $request->route($foreignKey);

        // Get the team model
        // @todo: Throw exception or something to know that team not found
        $team = TeamFacade::instance('team')->findOrFail($foreignId);

        // Check the ability
        if ($action === 'hasTeamAbility') {
            return $this->checkTeamAbility($request, $team, reset($params), $models);
        }

        return !Auth::guest() && Auth::user()?->$action($team, $params, $require);
    }

    /**
     * Check user's ability for the team.
     */
    protected function checkTeamAbility(Request $request, $team, string $ability, ?array $models): bool
    {
        // Get the models for ability check
        [$entityClass, $entityId] = $this->getGateArguments($request, $models);

        // Ensure entity id is provided
        if ($entityId === null) {
            return false;
        }

        // Fetch the entity model or return false if not found
        $entity = $entityClass::find($entityId);

        // @todo: Throw exception or something to know that entity not exists
        if (!$entity) {
            return false;
        }

        // Check the ability for the entity for the current user
        return $request->user()->hasTeamAbility($team, $ability, $entity);
    }

    /**
     * The request is unauthorized, so it handles the aborting/redirecting.
     */
    protected function unauthorized(): RedirectResponse
    {
        // Method to be called in the middleware return
        $handling = Config::get('teams.middleware.handling');
        $handler = Config::get('teams.middleware.handlers.' . $handling);

        if ($handling === 'abort') {
            abort($handler['code'], $handler['message']);
        }

        // Prepare redirect response
        $redirect = redirect()->to($handler['url']);

        // If flash message key is provided, use it for session message
        if (!empty($handler['message']['key']) && !empty($handler['message']['content'])) {
            $redirect->with($handler['message']['key'], $handler['message']['content']);
        }

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
        return array_map(fn ($model) => $model instanceof Model ? $model : $this->getModel($request, $model), $models);
    }

    /**
     * Get the model to authorize.
     *
     * @param Request $request
     * @param string $model
     * @return string
     */
    protected function getModel(Request $request, string $model): string
    {
        // Trim the model name and ensure it is a fully qualified class name if not already
        $trimmedModel = trim($model);

        // Return the model directly if it is a fully qualified class name
        if (class_exists($trimmedModel)) {
            return $trimmedModel;
        }

        // Otherwise, retrieve the model from the request route, falling back to the original model name
        return $request->route($trimmedModel) ?: $trimmedModel;
    }
}
