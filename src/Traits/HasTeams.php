<?php

namespace Jurager\Teams\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Jurager\Teams\Support\Facades\Teams;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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
     * @return array|string[]
     */
    public function teamPermissions(object $team): array
    {
        // If the user is the team owner, grant him all rights.
        if ($this->ownsTeam($team)) {
            return ['*'];
        }

        // If the user does not belong to the team, return an empty array of rights.
        if (! $this->belongsToTeam($team)) {
            return [];
        }

        // Get the user's role in the team.
        $role = $this->teamRole($team);

        // Defaults get role permission
        $permissions = $role->permissions->pluck('code')->all();

        // Get all team groups for user
        $groups = $this->groups()->where(config('teams.foreign_keys.team_id', 'team_id'), $team->id)->get();

        // Filling in group permissions
        foreach ($groups as $group) {
            $permissions = [...$permissions, ...$group->permissions->pluck('code')->all()];
        }

        // Return permissions.
        return $permissions;
    }

    /**
     * Determine if the user has the given permission on the given team.
     *
     * @param object $team
     * @param string|array $permissions
     * @param bool $require
     * @return bool
     */
    public function hasTeamPermission(object $team, string|array $permissions = [], bool $require = false): bool
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
        if (empty($permissions) && !$require) {
            return true;
        }
        // Get user's permissions for the team
        $user_permissions = $this->teamPermissions($team);

        // Check simple permission
        $check_permission = static function ($permission) use ($user_permissions) {

            // Calculate wildcard permissions
            $calculated_permissions = [...array_map(static fn ($part) => $part . '.*', explode('.', $permission)), $permission];

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
            'entity_id' => $entity->id,
            'entity_type' => $entity::class
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
        $calculated_permissions = [...array_map(static fn ($part) => $part.'.*', explode('.', $ability)), $ability];

        // Check if user has any of the calculated permissions
        $common_permissions = array_intersect($calculated_permissions, $permissions);

        // Return true if the permissions is found and not required
        return ! empty($common_permissions);
    }

    /**
     * Determinate if user can perform an action
     * @todo: Refactor this
     *
     * @param object $team
     * @param string $ability
     * @param object $entity
     * @return bool
     */
    public function hasTeamAbility(object $team, string $ability, object $entity): bool
    {
        // Check if user is entity owner by custom method
        if ((method_exists($entity, 'isOwner') && $entity->isOwner($this))) {
            return true;
        }

        // The meaning of the default access levels
        $allowed = 0;
        $forbidden = 1;

        if ($this->hasTeamPermission($team, $ability)) {
            $allowed = 1;
        }

        if ($this->hasGlobalGroupPermissions($ability)) {
            $allowed = 2;
        }

        // Get an ability
        $ability = Teams::instance('ability')->firstWhere([
            config('teams.foreign_keys.team_id', 'team_id') => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability,
        ]);

        // If there is a rule for an entity
        if ($ability) {

            // Get permissions
            $permissions = Teams::instance('permission')->where([
                config('teams.foreign_keys.team_id', 'team_id') => $team->id,
                'ability_id' => $ability->id,
            ])->get();

            $role = $this->teamRole($team);
            $groups = $this->groups->where(config('teams.foreign_keys.team_id', 'team_id'), $team->id);

            // Check permissions for role, group, and user
            $entities_to_check = [$role, ...$groups, $this];

            $access_levels = [
                Teams::model('role') => [
                    'allowed' => 1,
                    'forbidden' => 2,
                ],
                Teams::model('group') => [
                    'allowed' => 2,
                    'forbidden' => 3,
                ],
                $this::class => [
                    'allowed' => 3,
                    'forbidden' => 4,
                ],
            ];

            foreach ($entities_to_check as $item) {

                // Checking if $item exists
                if (! isset($item)) {
                    continue;
                }

                $permission = $permissions->firstWhere(['entity_id' => $item->id, 'entity_type' => $item::class]);

                if ($permission) {
                    if ($permission->forbidden) {
                        $forbidden = $access_levels[$item::class]['forbidden'];
                    } else {
                        $allowed = $access_levels[$item::class]['allowed'];
                    }
                }
            }

        }

        // Access level comparison
        return $allowed >= $forbidden;
    }

    /**
     * Allow user to perform an ability
     * @todo: Refactor this
     *
     * @param object $team
     * @param string $ability
     * @param object $entity
     * @param object|null $group
     * @return bool
     */
    public function allowTeamAbility(object $team, string $ability, object $entity, ?object $group = null): bool
    {
        // Determine the ability required to edit the entity
        $requiredAbility = $this->generateAbilityName($entity, 'edit');

        // Check if the user has the required ability to edit the entity
        if (! $this->hasTeamAbility($team, $requiredAbility, $entity)) {
            return false;
        }

        // Get or create the ability model for the specified action
        $ability_model = Teams::instance('ability')->firstOrCreate([
            config('teams.foreign_keys.team_id', 'team_id') => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability,
        ]);

        // Ensure the ability model is successfully retrieved or created
        if (! $ability_model) {
            throw new ModelNotFoundException("Ability with name '$ability' not found.");
        }

        // Update or create permission for the user entity to perform the action on the target
        Teams::instance('permission')->updateOrCreate(
            [
                config('teams.foreign_keys.team_id', 'team_id') => $team->id,
                'ability_id' => $ability_model->id,
                'entity_id' => $group->id ?? $this->id,
                'entity_type' => $group::class ?? $this::class,
            ],
            [
                'forbidden' => false,
            ]
        );

        return true;
    }

    /**
     * Forbid user to perform an ability
     * @todo: Refactor this
     *
     * @param object $team
     * @param string $ability
     * @param object $entity
     * @param object|null $group
     * @return bool
     */
    public function forbidTeamAbility(object $team, string $ability, object $entity, ?object $group): bool
    {
        // Determine the ability required to edit the entity
        $requiredAbility = $this->generateAbilityName($entity, 'edit');

        // Check if the user has the required ability to edit the entity
        if (! $this->hasTeamAbility($team, $requiredAbility, $entity)) {
            return false;
        }

        // Get an ability to perform an action on specific entity object inside team
        //
        $ability_model = Teams::instance('ability')->firstOrCreate([
            config('teams.foreign_keys.team_id', 'team_id') => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability,
        ]);

        // Ensure the ability model is successfully retrieved or created
        if (! $ability_model) {
            throw new ModelNotFoundException("Ability with name '$ability' not found.");
        }

        // Update or create permission for the user entity to perform the action on the target
        Teams::instance('permission')->updateOrCreate(
            [
                config('teams.foreign_keys.team_id', 'team_id') => $team->id,
                'ability_id' => $ability_model->id,
                'entity_id' => $group->id ?? $this->id,
                'entity_type' => $group::class ?? $this::class,
            ],
            [
                'forbidden' => true,
            ]
        );

        return true;
    }

    /**
     * Delete user ability
     * @todo: Refactor this
     *
     * @param object $team
     * @param string $ability
     * @param object $entity
     * @param object|null $group
     * @return bool
     */
    public function deleteTeamAbility(object $team, string $ability, object $entity, ?object $group): bool
    {
        // Determine the ability required to edit the entity
        $requiredAbility = $this->generateAbilityName($entity, 'edit');

        // Check if the user has the required ability to edit the entity
        if (! $this->hasTeamAbility($team, $requiredAbility, $entity)) {
            return false;
        }

        // Get an ability to perform an action on specific entity object inside team
        //
        $ability_model = Teams::instance('ability')->firstWhere([
            config('teams.foreign_keys.team_id', 'team_id') => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability,
        ]);

        // Ensure the ability model is successfully retrieved or created
        if (! $ability_model) {
            throw new ModelNotFoundException("Ability with name '$ability' not found.");
        }

        // Find permission for the user entity to perform the action on the target
        $permission = Teams::instance('permission')->firstWhere([
            config('teams.foreign_keys.team_id', 'team_id') => $team->id,
            'ability_id' => $ability_model->id,
            'entity_id' => $group->id ?? $this->id,
            'entity_type' => $group::class ?? $this::class,
        ]);

        if ($permission->delete()) {
            return true;
        }

        return false;
    }

    /**
     * Helper method for generating ability name
     *
     * @param object $entity
     * @param string $action
     * @return string
     */
    protected function generateAbilityName(object $entity, string $action): string
    {
        return Str::snake(Str::plural(class_basename($entity))) . '.' . $action;
    }
}
