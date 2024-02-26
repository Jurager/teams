<?php

namespace Jurager\Teams\Traits;

use Jurager\Teams\Owner;
use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait HasTeams
{
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
        return $this->hasMany(Teams::$teamModel);
    }

    /**
     * Get all the teams the user belongs to.
     *
     * @return BelongsToMany
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
     *
     * @param $team
     * @return bool
     */
    public function ownsTeam($team): bool
    {
        if ($team === null) {
            return false;
        }

        return $this->id == $team->{$this->getForeignKey()};
    }

    /**
     * Determine if the user is a tech support
     *
     * @return bool
     */
    private function isSupport(): bool
    {
        return $this->{config('teams.support_field', 'is_support')} ?? false;
    }

    /**
     * @return BelongsToMany
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$groupModel, 'user_group', 'user_id', 'group_id');
    }

    /**
     * Determine if the user belongs to the given team.
     *
     * @param  $team
     * @return bool
     */
    public function belongsToTeam($team): bool
    {
        // If the user is the owner of the team, then we consider that he belongs to it.
        if ($this->ownsTeam($team)) {
            return true;
        }

        // We check whether the user has access to the command by identifier.
        return $this->teams()->where('team_id', $team?->id)->exists();
    }

    /**
     * Get the role that the user has on the team.
     *
     * @param  $team
     * @return mixed
     */
    public function teamRole($team): mixed
    {
        // If the user is the owner of the team, return the Owner object.
        if ($this->ownsTeam($team)) {
            return new Owner();
        }

        // If the user does not belong to the team, return null.
        if (!$this->belongsToTeam($team)) {
            return null;
        }

        // Get the user's role in the team.
        $role = $team->users->where('id', $this->id)->first()->membership->role;
        
        // If the user has a role, return the role object, otherwise return null.
        return $role ? $team->findRole($role->id) : null;
    }

    /**
     * Determine if the user has the given role on the given team.
     *
     * @param  $team
     * @param string|array $roles
     * @param bool $require
     * @return bool
     */
    public function hasTeamRole($team, string|array $roles, bool $require = false): bool
    {
        // If the user owns the team, he has all the roles.
        if ($this->ownsTeam($team)) {
            return true;
        }

        // If the user does not belong to the team, return false.
        if (!$this->belongsToTeam($team)) {
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
            if ($user_role && $user_role->name === $role && !$require) {
                return true;
            }

            // If the user does not have at least one of the roles and $require is true, then we return false.
            if (!$user_role || $user_role->name !== $role && $require) {
                return false;
            }
        }

        return $require;
    }

    /**
     * Get the user's permissions for the given team.
     *
     * @param  $team
     * @return array
     */
    public function teamPermissions($team): array
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
        return (!$role) ? [] : $role->permissions;
    }

    /**
     * Determine if the user has the given permission on the given team.
     *
     * @param  $team
     * @param string|array $permissions
     * @param bool $require
     * @return bool
     */
    public function hasTeamPermission($team, string|array $permissions, bool $require = false): bool
    {
        // Allow tech support or team owner unrestricted access
        if ($this->isSupport() || $this->ownsTeam($team)) {
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
            return !$require;
        }

        // Get user's permissions for the team
        $user_permissions = $this->teamPermissions($team);

        // Check each permission
        foreach ($permissions as $permission) {

            // Calculate wildcard permissions
            $calculated_permissions = [...array_map(static fn($part) => $part . '.*', explode('.', $permission)), $permission];

            // Check if user has any of the calculated permissions
            $has_permission = !empty(array_intersect($calculated_permissions, $user_permissions));

            // Return true if the permission is found and not required
            if ($has_permission && !$require) {
                return true;
            }

            // Return false if the permission is required but not found
            if (!$has_permission && $require) {
                return false;
            }

        }

        // If all permissions have been checked and nothing matched, return the value of $require
        return $require;
    }

    /**
     * Get all abilities to specific entity
     *
     * @param $team
     * @param $entity
     * @param bool $forbidden
     * @return mixed
     */
    public function teamAbilities($team, $entity, bool $forbidden = false): mixed
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
     * Determinate if user can perform an action
     *
     * @param $team
     * @param $ability
     * @param $entity
     * @param bool $require
     * @return bool
     *
     */
    public function hasTeamAbility($team, $ability, $entity, bool $require = false): bool
    {
        // Check if user is tech support or entity owner
        // Check permission by role properties
        if ($this->isSupport() || method_exists($entity, 'isOwner') && $entity?->isOwner($this) || $this->hasTeamPermission($team, $ability)) {
            return true;
        }

        // Get an ability
        $ability = Teams::$abilityModel::firstWhere([
            'team_id' => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability
        ]);

        // If there is a rule for an entity
        if ($ability) {

            // Get permissions
            $permissions = Teams::$permissionModel::where([
                'team_id' => $team->id,
                'ability_id' => $ability->id,
            ])->get();

            $role = $this->teamRole($team);
            $group = $this->groups()->firstWhere('team_id', $team->id);

            // Check permissions for role, group, and user
            $entities_to_check = [$role, $group, $this];

            foreach ($entities_to_check as $entity) {

                $permission = $permissions->firstWhere(['entity_id' => $entity?->id, 'entity_type' => $entity::class]);

                if ($permission) {
                    return !$permission->forbidden;
                }
            }

        }

        return false;
    }

    /**
     * Allow user to perform an ability
     *
     * @param $team
     * @param string $ability
     * @param $entity
     * @param $target
     * @return bool
     */
    public function allowTeamAbility($team, string $ability, $entity, $target): bool
    {
        // Determine the ability required to edit the entity
        $ability_to_edit = lcfirst(class_basename($entity)) . 's.edit';

        // Check if the user has the required ability to edit the entity
        if (!$this->hasTeamAbility($team, $ability_to_edit, $entity)) {
            return false;
        }

        // Get or create the ability model for the specified action
        $ability_model = Teams::$abilityModel::firstOrCreate([
            'team_id' => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability
        ]);

        // Ensure the ability model is successfully retrieved or created
        if (!$ability_model) {
            return false;
        }

        // Update or create permission for the user entity to perform the action on the target
        Teams::$permissionModel::updateOrCreate(
            [
                'team_id'     => $team->id,
                'ability_id'  => $ability_model->id,
                'entity_id'   => $target->id,
                'entity_type' => get_class($target)
            ],
            [
                'forbidden'   => false
            ]
        );

        return true;
    }

    /**
     * Forbid user to perform an ability
     *
     * @param $team
     * @param string $ability
     * @param $entity
     * @param $target
     * @return bool
     */
    public function forbidTeamAbility($team, string $ability, $entity, $target): bool
    {
        // Determine the ability required to edit the entity
        $ability_to_edit = lcfirst(class_basename($entity)) . 's.edit';

        // Check if the user has the required ability to edit the entity
        if (!$this->hasTeamAbility($team, $ability_to_edit, $entity)) {
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
        if (!$ability_model) {
            return false;
        }

        // Update or create permission for the user entity to perform the action on the target
        Teams::$permissionModel::updateOrCreate(
            [
                'team_id'     => $team->id,
                'ability_id'  => $ability_model->id,
                'entity_id'   => $target->id,
                'entity_type' => get_class($target)
            ],
            [
                'forbidden'   => true
            ]
        );

        return true;
    }

    /**
     * Delete user ability
     *
     * @param $team
     * @param string $ability
     * @param $entity
     * @param $target
     * @return bool
     */
    public function deleteTeamAbility($team, string $ability, $entity, $target): bool
    {
        // Determine the ability required to edit the entity
        $ability_to_edit = lcfirst(class_basename($entity)) . 's.edit';

        // Check if the user has the required ability to edit the entity
        if (!$this->hasTeamAbility($team, $ability_to_edit, $entity)) {
            return false;
        }

        // Get an ability to perform an action on specific entity object inside team
        //
        $ability_model = Teams::$abilityModel::firstWhere([
            'team_id' => $team->id,
            'entity_id' => $entity->id,
            'entity_type' => $entity::class,
            'name' => $ability
        ]);

        // Ensure the ability model is successfully retrieved or created
        if (!$ability_model) {
            return false;
        }

        // Find permission for the user entity to perform the action on the target
        $permission = Teams::$permissionModel::firstWhere([
            'team_id'     => $team->id,
            'ability_id'  => $ability_model->id,
            'entity_id'   => $target->id,
            'entity_type' => get_class($target)
        ]);

        if ($permission->delete()) {
            return true;
        }

        return false;
    }
}
