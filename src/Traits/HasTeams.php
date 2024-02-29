<?php

namespace Jurager\Teams\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Jurager\Teams\Owner;
use Jurager\Teams\Teams;

trait HasTeams
{
    /**
     * Get all the teams the user owns or belongs to.
     */
    public function allTeams(): Collection
    {
        return $this->ownedTeams->merge($this->teams)->sortBy('name');
    }

    /**
     * Get all the teams the user owns.
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Teams::$teamModel);
    }

    /**
     * Get all the teams the user belongs to.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$teamModel, Teams::$membershipModel)
            ->withPivot('role_id')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Determine if the user owns the given team.
     */
    public function ownsTeam(object $team): bool
    {
        if ($team === null) {
            return false;
        }

        return $this->id === $team->{$this->getForeignKey()};
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$groupModel, 'user_group', 'user_id', 'group_id');
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(object $team): bool
    {
        // We check whether the user has access to the team by identifier.
        return $this->ownsTeam($team) || $this->teams()->where('team_id', $team?->id)->exists();
    }

    /**
     * Get the role that the user has on the team.
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
        $role = $team->users->where('id', $this->id)->first()->membership->role;

        // If the user has a role, return the role object, otherwise return null.
        return $role ? $team->findRole($role->id) : null;
    }

    /**
     * Determine if the user has the given role on the given team.
     */
    public function hasTeamRole(object $team, string|array $roles, bool $require = false): bool
    {
        // If the user owns the team, he has all the roles.
        if ($this->ownsTeam($team)) {
            return true;
        }

        // If the user does not belong to the team, return false.
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        // Convert a string to an array if a single role is passed.
        $roles = (array) $roles;

        // If the list of roles is empty, then we return true or false depending on $require.
        if (empty($roles)) {
            return $require;
        }

        // Checking roles.
        foreach ($roles as $role) {

            // Obtain the user's role in the team.
            $user_role = $team->findRole($team->users->where('id', $this->id)->first()->membership->role->id);

            // If the user has at least one of the roles and $require is false, then we return true.
            if ($user_role && $user_role->name === $role && ! $require) {
                return true;
            }

            // If the user does not have at least one of the roles and $require is true, then we return false.
            if (! $user_role || ($user_role->name !== $role && $require)) {
                return false;
            }
        }

        return $require;
    }

    /**
     * Get the user's permissions for the given team.
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

        // Return the role's permissions.
        return (! $role) ? [] : $role->permissions;
    }

    /**
     * Determine if the user has the given permission on the given team.
     */
    public function hasTeamPermission(object $team, string|array $permissions, bool $require = false): bool
    {
        if ($this->ownsTeam($team)) {
            return true;
        }

        // If the user does not belong to the team, deny access
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        // Convert a string to an array if a single permission is passed.
        $permissions = (array) $permissions;

        // If the permission array is empty, return true if not required, false otherwise
        if (empty($permissions)) {
            return true;
        }

        // Get user's permissions for the team
        $user_permissions = $this->teamPermissions($team);

        // Check simple permission
        $check_permission = static function ($permission) use ($user_permissions) {

            // Calculate wildcard permissions
            $calculated_permissions = [...array_map(static fn ($part) => $part.'.*', explode('.', $permission)), $permission];

            // Check if user has any of the calculated permissions
            $common_permissions = array_intersect($calculated_permissions, $user_permissions);

            // Return true if the permission is found and not required
            return ! empty($common_permissions);
        };

        // Check each permission
        foreach ($permissions as $permission) {
            $has_permission = $check_permission($permission);

            if ($has_permission && ! $require) {
                return true;
            }

            if (! $has_permission && $require) {
                return false;
            }
        }

        // If all permissions have been checked and nothing matched, return the value of $require
        return true;
    }

    /**
     * Get all abilities to specific entity
     */
    public function teamAbilities(object $team, object $entity, bool $forbidden = false): mixed
    {
        // Start building the query to retrieve permissions
        $permissions = Teams::$permissionModel::where('team_id', $team->id);

        // If filtering by forbidden permissions, add the condition
        if ($forbidden) {
            $permissions->where('forbidden', true);
        }

        // Filter permissions based on the entity
        $permissions->whereHas('ability', static function ($query) use ($entity) {
            $query->where(['entity_id' => $entity->id, 'entity_type' => $entity::class]);
        });

        // Retrieve the permissions along with their associated abilities
        return $permissions->with('ability')->get();
    }

    /**
     * Determinate if user has ability outside team
     *
     * This function is employed to verify abilities within a universal group,
     * especially in cases where a team requires a group enabling user additions
     * and removals without direct affiliation with the team
     *
     * Example: Each team should have a global group of moderators.
     */
    private function hasAbility(string $ability): bool
    {
        // Get all global groups
        $groups = $this->groups->whereNull('team_id');

        $capabilities = [];

        foreach ($groups as $group) {

            $group->load('capabilities');

            // All user permissions from global groups
            $capabilities = [...$capabilities, ...$group->permissions];
        }

        // Calculate wildcard permissions
        $calculated_permissions = [...array_map(static fn ($part) => $part.'.*', explode('.', $ability)), $ability];

        // Check if user has any of the calculated permissions
        $common_permissions = array_intersect($calculated_permissions, $capabilities);

        // Return true if the permission is found and not required
        return ! empty($common_permissions);
    }

    /**
     * Determinate if user can perform an action
     */
    public function hasTeamAbility(object $team, string $ability, object $entity): bool
    {
        // Check if user is tech support or entity owner
        // Check permission by role properties
        if ((method_exists($entity, 'isOwner') && $entity?->isOwner($this))) {
            return true;
        }

        // The meaning of the default access levels
        $allowed = 0;
        $forbidden = 1;

        if ($this->hasTeamPermission($team, $ability)) {
            $allowed = 1;
        }

        if ($this->hasAbility($ability)) {
            $allowed = 2;
        }

        // Get an ability
        $ability = Teams::$abilityModel::firstWhere([
            'team_id' => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability,
        ]);

        // If there is a rule for an entity
        if ($ability) {

            // Get permissions
            $permissions = Teams::$permissionModel::where([
                'team_id' => $team->id,
                'ability_id' => $ability->id,
            ])->get();

            $role = $this->teamRole($team);
            $groups = $this->groups->where('team_id', $team->id);

            // Check permissions for role, group, and user
            $entities_to_check = [$role, ...$groups, $this];

            $access_levels = [
                Teams::$roleModel => [
                    'allowed' => 1,
                    'forbidden' => 2,
                ],
                Teams::$groupModel => [
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
        //
        return $allowed >= $forbidden;
    }

    /**
     * Allow user to perform an ability
     */
    public function allowTeamAbility(object $team, string $ability, object $entity, ?object $group = null): bool
    {
        // Determine the ability required to edit the entity
        $ability_to_edit = lcfirst(class_basename($entity)).'s.edit';

        // Check if the user has the required ability to edit the entity
        if (! $this->hasTeamAbility($team, $ability_to_edit, $entity)) {
            return false;
        }

        // Get or create the ability model for the specified action
        $ability_model = Teams::$abilityModel::firstOrCreate([
            'team_id' => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability,
        ]);

        // Ensure the ability model is successfully retrieved or created
        if (! $ability_model) {
            return false;
        }

        // Update or create permission for the user entity to perform the action on the target
        Teams::$permissionModel::updateOrCreate(
            [
                'team_id' => $team->id,
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
     */
    public function forbidTeamAbility(object $team, string $ability, object $entity, ?object $group): bool
    {
        // Determine the ability required to edit the entity
        $ability_to_edit = lcfirst(class_basename($entity)).'s.edit';

        // Check if the user has the required ability to edit the entity
        if (! $this->hasTeamAbility($team, $ability_to_edit, $entity)) {
            return false;
        }

        // Get an ability to perform an action on specific entity object inside team
        //
        $ability_model = Teams::$abilityModel::firstOrCreate([
            'team_id' => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability,
        ]);

        // Ensure the ability model is successfully retrieved or created
        if (! $ability_model) {
            return false;
        }

        // Update or create permission for the user entity to perform the action on the target
        Teams::$permissionModel::updateOrCreate(
            [
                'team_id' => $team->id,
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
     */
    public function deleteTeamAbility(object $team, string $ability, object $entity, ?object $group): bool
    {
        // Determine the ability required to edit the entity
        $ability_to_edit = lcfirst(class_basename($entity)).'s.edit';

        // Check if the user has the required ability to edit the entity
        if (! $this->hasTeamAbility($team, $ability_to_edit, $entity)) {
            return false;
        }

        // Get an ability to perform an action on specific entity object inside team
        //
        $ability_model = Teams::$abilityModel::firstWhere([
            'team_id' => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability,
        ]);

        // Ensure the ability model is successfully retrieved or created
        if (! $ability_model) {
            return false;
        }

        // Find permission for the user entity to perform the action on the target
        $permission = Teams::$permissionModel::firstWhere([
            'team_id' => $team->id,
            'ability_id' => $ability_model->id,
            'entity_id' => $group->id ?? $this->id,
            'entity_type' => $group::class ?? $this::class,
        ]);

        if ($permission->delete()) {
            return true;
        }

        return false;
    }
}
