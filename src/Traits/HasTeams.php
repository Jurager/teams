<?php

namespace Jurager\Teams\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Jurager\Teams\Support\Facades\Teams;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Jurager\Teams\Models\Owner;

trait HasTeams
{
    /**
     * Determine if the user owns the given team.
     *
     * @param object $team
     * @return bool
     */
    public function ownsTeam(object $team): bool
    {
        return $this->id === $team->{$this->getForeignKey()};
    }

    /**
     * Get all the teams the user owns or belongs to.
     *
     * @return Collection
     */
    public function allTeams(): Collection
    {
        return $this->ownedTeams->merge($this->teams)->sortBy('name');
    }

    /**
     * Get all the teams the user owns.
     *
     * @return HasMany
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Teams::model('team'))->setEagerLoads([]);
    }


    /**
     * Get all the teams the user belongs to.
     *
     * @return BelongsToMany
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('team'), Teams::model('membership'), 'user_id', config('teams.foreign_keys.team_id'))
            ->setEagerLoads([])
            ->withPivot('role_id')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Get the abilities that belongs to user.
     *
     * @return MorphToMany
     */
    public function abilities(): MorphToMany
    {
        return $this->morphToMany(Teams::model('ability'), 'entity', 'entity_ability')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Get all the groups the user belongs to.
     *
     * @return BelongsToMany
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('group'), 'group_user', 'user_id', 'group_id');
    }

    /**
     * Determine if the user belongs to the given team.
     *
     * @param object $team
     * @return bool
     */
    public function belongsToTeam(object $team): bool
    {
        return $this->ownsTeam($team) || $this->teams()->where(config('teams.foreign_keys.team_id', 'team_id'), $team->id)->exists();
    }

    /**
     * Get the role that the user has on the team.
     *
     * @param object $team
     * @return mixed
     */
    public function teamRole(object $team): mixed
    {
        // If the user is the owner of the team, return the Owner object.
        if ($this->ownsTeam($team)) {
            return new Owner();
        }

        // If the user does not belong to the team, return null.
        if (! $this->belongsToTeam($team)) {
            return null;
        }

        // Get the user's role in the team.
        return $team->getRole($team->users->firstWhere('id', $this->id)->membership->role->id ?? null);
    }


    /**
     * Determine if the user has the given role on the given team.
     *
     * @param object $team
     * @param string|array $roles
     * @param bool $require
     * @return bool
     */
    public function hasTeamRole(object $team, string|array $roles, bool $require = false): bool
    {
        if ($this->ownsTeam($team)) {
            return true;
        }

        if (! $this->belongsToTeam($team)) {
            return false;
        }

        // Convert a string to an array if a single role is passed.
        $roles = (array) $roles;

        // If the list of roles is empty, then we return true or false depending on $require.
        if (empty($roles)) {
            return $require;
        }

        $userRole = $team->users->firstWhere('id', $this->id)->membership->role->code ?? null;

        if (!$userRole) {
            return false;
        }

        // For require=true, check that the user role matches all passed roles.
        if ($require) {
            return empty(array_diff($roles, [$userRole]));
        }

        // For require=false, return true if at least one of the roles matches the user's role.
        return in_array($userRole, $roles, true);
    }

    /**
     * Get the user's permissions for the given team.
     *
     * @param object $team
     * @param string|null $scope Scope of permissions to get (ex. 'role', 'group'), by default getting all permissions
     * @return array|string[]
     */
    public function teamPermissions(object $team, string|null $scope = null): array
    {
        // If the user is the team owner, grant him all rights.
        if ($this->ownsTeam($team)) {
            return ['*'];
        }

        // If the user does not belong to the team, return an empty array of rights.
        if (! $this->belongsToTeam($team)) {
            return [];
        }

        $permissions = [];

        // Get role-based permissions if the scope is 'role' or null (both)
        if (!$scope || $scope === 'role') {
            $permissions = $this->teamRole($team)->permissions->pluck('code')->all();
        }

        // Append group-based permissions if the scope is 'group' or null (both)
        if (!$scope || $scope === 'group') {
            $groupPermissions = $this->groups()
                ->where(config('teams.foreign_keys.team_id', 'team_id'), $team->id)
                ->get()
                ->flatMap(fn($group) => $group->permissions->pluck('code'))
                ->all();

            $permissions = array_merge($permissions, $groupPermissions);
        }

        // Return unique permissions
        return array_unique($permissions);
    }

    /**
     * Determine if the user has the given permission on the given team.
     *
     * @param object $team
     * @param string|array $permissions
     * @param bool $require
     * @param string|null $scope Scope of permissions to check (ex. 'role', 'group'), by default checking all permissions
     * @return bool
     */
    public function hasTeamPermission(object $team, string|array $permissions, bool $require = false, string|null $scope = null): bool
    {
        //$require = true  (all permissions in the array are required)
        //$require = false  (only one or more permission in the array are required or $permissions is empty)

        if ($this->ownsTeam($team)) {
            return true;
        }

        // If the user does not belong to the team, deny access
        if (!$this->belongsToTeam($team)) {
            return false;
        }

        // Convert a string to an array if a single permission is passed.
        $permissions = (array) $permissions;

        // If the permissions array is empty, return true if not required, false otherwise
        if (empty($permissions)) {
            return false;
        }

        // Get user's permissions for the team
        $user_permissions = $this->teamPermissions($team, $scope);

        // Check simple permission
        $check_permission = static function ($permission) use ($user_permissions) {

            // Calculate wildcard permissions
            $calculated_permissions = [...array_map(fn ($part) => $part . '.*', explode('.', $permission)), $permission];

            // Check if user has any of the calculated permissions
            $common_permissions = array_intersect($calculated_permissions, $user_permissions);

            // Return true if the permission is found and not required
            return !empty($common_permissions);
        };

        // Check each permission
        foreach ($permissions as $permission) {
            $has_permission = $check_permission($permission);

            // $require == false  (only one or more permissions in the array are required or $permissions is empty)
            // return true after first permission found
            if ($has_permission && !$require) {
                return true;
            }

            // $require == true  (all permissions in the array are required)
            // return false after first permission found
            if (!$has_permission && $require) {
                return false;
            }
        }

        // return $require, cause if $require is true all the checks has been made, and if false you don't have the required permissions
        return $require;
    }

    /**
     * Get all ability that specific entity within team
     *
     * @param object $team
     * @param object $entity
     * @param bool $forbidden
     * @return mixed
     */
    public function teamAbilities(object $team, object $entity, bool $forbidden = false): mixed
    {
        // Start building the query to retrieve abilities
        $abilities = $this->abilities()->where([
            config('teams.foreign_keys.team_id', 'team_id') => $team->id,
            'abilities.entity_id' => $entity->id,
            'abilities.entity_type' => $entity::class
        ]);

        // If filtering by forbidden abilities, add the condition
        if ($forbidden) {
            $abilities->wherePivot('forbidden', true);
        }

        // Retrieve the abilities
        return $abilities->get();
    }

    /**
     * Determinate if user has global groups permissions
     *
     * This function is to verify permissions within a universal group.
     * Especially in cases where a team requires a group enabling user additions
     * and removals without direct affiliation with the team
     *
     * Example: Each team should have a global group of moderators.
     *
     * @param string $ability
     * @return bool
     */
    private function hasGlobalGroupPermissions(string $ability): bool
    {
        // Get all global groups
        $groups = $this->groups->whereNull(config('teams.foreign_keys.team_id', 'team_id'));

        $permissions = [];

        foreach ($groups as $group) {

            // Eager load a relationship after modify
            $group->load('permissions');

            // All user permissions from global groups
            $permissions = [...$permissions, ...$group->permissions->pluck('code')->all()];
        }

        // Calculate wildcard permission
        $calculated_permissions = [...array_map(fn ($part) => $part.'.*', explode('.', $ability)), $ability];

        // Check if user has any of the calculated permissions
        $common_permissions = array_intersect($calculated_permissions, $permissions);

        // Return true if the permissions is found and not required
        return ! empty($common_permissions);
    }

    /**
     * Determinate if user can perform an action
     *
     * @param object $team
     * @param string $permission
     * @param object $action_entity
     * @return bool
     */
    public function hasTeamAbility(object $team, string $permission, object $action_entity): bool
    {
        if (method_exists($action_entity, 'isOwner') && $action_entity->isOwner($this)) {
            return true;
        }

        $DEFAULT = 0;
        $FORBIDDEN = 1;
        $ROLE_ALLOWED = 2;
        $ROLE_FORBIDDEN = 3;
        $GROUP_ALLOWED = 4;
        $GROUP_FORBIDDEN = 5;
        $USER_ALLOWED = 5;
        $USER_FORBIDDEN = 6;
        $GLOBAL_ALLOWED = 6;

        $allowed = $DEFAULT;
        $forbidden = $FORBIDDEN;

        if ($this->hasTeamPermission($team, $permission, scope: 'role')) {
            $allowed = max($allowed, $ROLE_ALLOWED);
        }

        if ($this->hasTeamPermission($team, $permission, scope: 'group')) {
            $allowed = max($allowed, $GROUP_ALLOWED);
        }

        if ($this->hasGlobalGroupPermissions($permission)) {
            $allowed = max($allowed, $GLOBAL_ALLOWED);
        }

        $ability = Teams::instance('ability')->firstWhere([
            config('teams.foreign_keys.team_id', 'team_id') => $team->id,
            'entity_id' => $action_entity->id,
            'entity_type' => get_class($action_entity),
            'permission_id' => $team->getPermissionIds([$permission])[0]
        ])->with(['users', 'groups', 'roles']);


        if ($ability) {

            $role = $this->teamRole($team);
            $groups = $this->groups->where(config('teams.foreign_keys.team_id', 'team_id'), $team->id);

            foreach ([$role, ...$groups, $this] as $entity) {

                if (!isset($entity)) {
                    continue;
                }

                $relation = $this->getRelationName($entity);
                $foundEntity = $ability->{$relation}->firstWhere('id', $entity->id);

                if ($foundEntity) {

                    $isForbidden = $foundEntity->pivot->forbidden;

                    if ($isForbidden) {
                        $forbidden = max($forbidden,$relation === 'role' ? $ROLE_FORBIDDEN : ($relation === 'group' ? $GROUP_FORBIDDEN : $USER_FORBIDDEN));
                    } else {
                        $allowed = max($allowed,$relation === 'role' ? $ROLE_ALLOWED : ($relation === 'group' ? $GROUP_ALLOWED : $USER_ALLOWED));
                    }
                }
            }
        }

        return $allowed >= $forbidden;
    }

    /**
     * Allow user to perform an ability on entity
     *
     * @param object $team
     * @param string $permission
     * @param object $action_entity
     * @param object|null $target_entity
     * @return void
     */
    public function allowTeamAbility(object $team, string $permission, object $action_entity, object|null $target_entity = null): void
    {
        $this->updateAbilityOnEntity($team, 'syncWithoutDetaching', $permission, $action_entity, $target_entity);
    }

    /**
     * Forbid user to perform an ability on entity
     *
     * @param object $team
     * @param string $permission
     * @param object $action_entity
     * @param object|null $target_entity
     * @return void
     */
    public function forbidTeamAbility(object $team, string $permission, object $action_entity, object|null $target_entity = null): void
    {
        $this->updateAbilityOnEntity($team, 'syncWithoutDetaching', $permission, $action_entity, $target_entity, true);
    }

    /**
     * Delete user ability on entity
     *
     * @param object $team
     * @param string $action
     * @param object $action_entity
     * @param object|null $target_entity
     * @return void
     *
     */
    public function deleteTeamAbility(object $team, string $permission, object $action_entity, object|null $target_entity = null): void
    {
        $this->updateAbilityOnEntity($team, 'detach', $permission, $action_entity, $target_entity);
    }

    /**
     * Helper method for attaching or detaching ability to entity
     *
     * @param object $team
     * @param string $method
     * @param string $permission
     * @param object $action_entity
     * @param object|null $target_entity
     * @param bool $forbidden
     * @return void
     */
    private function updateAbilityOnEntity(object $team, string $method, string $permission, object $action_entity, object|null $target_entity = null, bool $forbidden = false): void
    {
        $ability_model = Teams::instance('ability')->firstOrCreate([
            config('teams.foreign_keys.team_id', 'team_id') => $team->id,
            'entity_id' => $action_entity->id,
            'entity_type' => $action_entity::class,
            'permission_id' => $team->getPermissionIds([$permission])[0]
        ]);

        // Ensure the ability model is successfully retrieved or created
        if (! $ability_model) {
            throw new ModelNotFoundException("Ability with permission '$permission' not found.");
        }

        // Target for ability defaults to user
        $target_entity = $target_entity ?? $this;

        // Get relation name for ability
        $relation = $this->getRelationName($target_entity);

        if(!method_exists($ability_model, $relation)) {
            throw new ModelNotFoundException("Ability relation '$relation' not found.");
        }

        $ability_model->{$relation}()->{$method}($target_entity->id, [
            'forbidden' => $forbidden,
        ]);
    }

    /**
     * Get relation name for ability
     *
     * @param object|string $classname
     * @return string
     */
    private function getRelationName(object|string $classname): string
    {
        return  Str::plural(strtolower(class_basename( is_object($classname) ? $classname::class : $classname )));
    }
}