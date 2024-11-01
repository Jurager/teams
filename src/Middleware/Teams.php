<?php

namespace Jurager\Teams\Middleware;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jurager\Teams\Support\Facades\Teams as TeamFacade;
use InvalidArgumentException;

class Teams
{
    /**
     * Check if the request has authorization to continue.
     */
    protected function authorization(Request $request, string $method, string|array $params, ?string $teamId, ?array $models, bool $require = false): bool
    {
        // Mapping of method names
        $methodTypes = [
            'roles' => 'hasTeamRole',
            'permissions' => 'hasTeamPermission',
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
        $foreignKey = config('teams.foreign_keys.team_id', 'team_id');

        // If team id not directly passed, get the id from request or route param
        $foreignId = $teamId ?? $request->input($foreignKey) ?? $request->route($foreignKey);

        // Get the team model
        $team = TeamFacade::instance('team')->findOrFail($foreignId);

        // Check the ability
        if ($action === 'hasTeamAbility') {
            return $this->checkTeamAbility($request, $team, $params, $models);
        }

        // Check the permissions
        return !Auth::guest() && Auth::user()?->$action($team, $params, $require);
    }

    /**
     * Check user's ability for the team.
     */
    protected function checkTeamAbility(Request $request, $team, array $params, ?array $models): bool
    {
        // Get the models for ability check
        [$entityClass, $entityId] = $this->getGateArguments($request, $models);

        // Ensure entity id is provided
        if ($entityId === null) {
            return false;
        }

        // Fetch the entity model or return false if not found
        $entity = $entityClass::find($entityId);

        if (!$entity) {
            return false;
        }

        // Check the ability for the entity for the current user
        return $request->user()->hasTeamAbility($team, $params, $entity);
    }

    /**
     * The request is unauthorized, so it handles the aborting/redirecting.
     */
    protected function unauthorized(): RedirectResponse
    {
        // Method to be called in the middleware return
        $handling = config('teams.middleware.handling');
        $handler = config('teams.middleware.handlers.' . $handling);

        if ($handling === 'abort') {
            abort($handler['code'], $handler['message']);
        }

        // Prepare redirect response
        $redirect = redirect()->to($handler['url']);

        // If flash message key is provided, use it for session message
        if (!empty($handler['message']['key'])) {
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
        return array_map(fn($model) => $model instanceof Model ? $model : $this->getModel($request, $model), $models);
    }

    /**
     * Get the model to authorize.
     */
    protected function getModel(Request $request, $model): string
    {
        return str_contains($model, '\\') ? trim($model) : $request->route($model, $model);
    }
}
