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
     * Get all of the teams the user owns or belongs to.
     *
     * @return Collection
     */
    public function allTeams(): Collection
    {
        return $this->ownedTeams->merge($this->teams)->sortBy('name');
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return HasMany
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Teams::teamModel());
    }

    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Teams::teamModel(), Teams::membershipModel())
            ->withPivot('role')
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
        if (is_null($team)) {
            return false;
        }

        return $this->id == $team->{$this->getForeignKey()};
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
        return $this->teams->contains(function ($t) use ($team) {
            return $t->id === $team->id;
        }) || $this->ownsTeam($team);
    }

    /**
     * Get the role that the user has on the team.
     *
     * @param  $team
     * @return mixed
     */
    public function teamRole($team): mixed
    {
        if ($this->ownsTeam($team)) {
            return new Owner();
        }

        if (! $this->belongsToTeam($team)) {
            return null;
        }

        return $team->findRole($team->users->where('id', $this->id)->first()->membership->role);
    }

    /**
     * Determine if the user has the given role on the given team.
     *
     * @param  $team
     * @param string|array $role
     * @param bool $require
     * @return bool
     */
    public function hasTeamRole($team, string|array $role, bool $require = false): bool
    {
        if ($this->ownsTeam($team)) {
            return true;
        }

        if (is_array($role)) {
            if (empty($role)) {
                return true;
            }

            foreach ($role as $roleName) {
                $hasRole = $this->hasTeamRole($team, $roleName);

                if ($hasRole && !$require) {
                    return true;
                } elseif (!$hasRole && $require) {
                    return false;
                }
            }

            // If we've made it this far and $requireAll is FALSE, then NONE of the roles were found.
            // If we've made it this far and $requireAll is TRUE, then ALL of the roles were found.
            // Return the value of $requireAll.
            return $require;
        }

        return $this->belongsToTeam($team) && optional($team->findRole($team->users->where('id', $this->id)->first()->membership->role))->name === $role;
    }

    /**
     * Get the user's permissions for the given team.
     *
     * @param  $team
     * @return array
     */
    public function teamPermissions($team): array
    {
        if ($this->ownsTeam($team)) {
            return ['*'];
        }

        if (! $this->belongsToTeam($team)) {
            return [];
        }

        return $this->teamRole($team)->permissions;
    }

    /**
     * Determine if the user has the given permission on the given team.
     *
     * @param  $team
     * @param string|array $permission
     * @param bool $require
     * @return bool
     */
    public function hasTeamPermission($team, string|array $permission, bool $require = false): bool
    {
        if ($this->{config('teams.support_field', 'is_support')}) {
            return true;
        }

        if ($this->ownsTeam($team)) {
            return true;
        }


        if (! $this->belongsToTeam($team)) {
            return false;
        }

        if (is_array($permission)) {
            if (empty($permission)) {
                return true;
            }

            foreach ($permission as $permissionName) {
                $hasPermission = $this->hasTeamPermission($team, $permissionName);

                if ($hasPermission && !$require) {
                    return true;
                } elseif (!$hasPermission && $require) {
                    return false;
                }
            }

            // If we've made it this far and $requireAll is FALSE, then NONE of the perms were found.
            // If we've made it this far and $requireAll is TRUE, then ALL of the perms were found.
            // Return the value of $requireAll.
            return $require;
        }

        $permissions = $this->teamPermissions($team);

        $calculated  = [];
        $abilities 	 = explode('.', $permission);

        for ($i=1; $i < count($abilities); $i++) {
            $calculated[] = implode('.', array_slice($abilities, 0, $i)).'.*';
        }


        $calculated[] = $permission;


        foreach ($calculated as $item) {
            if (in_array($item, $permissions)) {
                return true;
            }
        }

        return false;


        //return in_array($permission, $permissions) ||
        //	in_array('*', $permissions) ||
        //	(Str::endsWith($permission, ':create') && in_array('*:create', $permissions)) ||
        //	(Str::endsWith($permission, ':update') && in_array('*:update', $permissions));
    }

    /**
     * Get all abilities to specific entity
     *
     * @param $team
     * @param $entity
     * @param bool $forbidden
     * @return mixed
     */
    public function teamAbilities($team, $entity, bool $forbidden = false)
    {
        $permissions = Teams::permissionModel()::where([
            'team_id'       => $team->id,
        ]);

        if ($forbidden) {
            $permissions = $permissions->where('forbidden', true);
        }

        return $permissions->whereHas('ability', function ($query) use ($entity) {
            $query->where(['entity_id' => $entity->id, 'entity_type' => $entity::class]);
        })->with('ability')->get();
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
     * 			Allow_level		Forbidden_level
     * Default		 0				  1
     * Role			 1				  2
     * Group		 2				  3
     * User			 3				  4
     *
     */
    public function hasTeamAbility($team, $ability, $entity, bool $require = false): bool
    {
        // Проверка, является ли пользователь техподдержкой
        if ($this->{config('teams.support_field', 'is_support')}) {
            return true;
        }

        // Проверка, является ли пользователь владельцем сущности
        if (method_exists($entity, 'isOwner') && $entity->isOwner($this)) {
            return true;
        }

        // Значение уровней доступа по умолчанию
        $allow_level = 0;
        $forbidden_level = 1;

        // Проверка разрешения по свойствам роли
        if ($this->hasTeamPermission($team, $ability)) {
            $allow_level = 1;
        }

        // Get an ability
        $ability = Teams::abilityModel()::where(['name' => $ability, 'entity_id' => $entity->id, 'entity_type' => $entity::class, 'team_id' => $team->id])->first();

        // Если существует правило для сущности
        if ($ability) {

            // Получение ограничений по сущности
            $permissions = Teams::permissionModel()::where([
                'team_id'       => $team->id,
                'ability_id'    => $ability->id,
            ])->get();

            // Роль пользователя
            $role   = $this->teamRole($team);
            // Группа пользователя
            $group  = $this->groups()->where('team_id', $team->id)->first();

            // Получение ограничений по роли
            $permission = $permissions->where('entity_id', $role->id)->firstWhere('entity_type', $role::class);

            // Если возможность запрещена для роли
            if ($permission && $permission->forbidden) {
                $forbidden_level = 2;
            }

            // Если пользователь прикреплен к группе
            if ($group) {
                // Получение ограничений по группе
                $permission = $permissions->where('entity_id', $group->id)->firstWhere('entity_type', $group::class);

                if ($permission) {
                    if ($permission->forbidden) {
                        $forbidden_level = 3;
                    } else {
                        $allow_level = 2;
                    }
                }
            }

            // Получение ограничений по пользователю
            $permission = $permissions->where('entity_id', $this->id)->firstWhere('entity_type', $this::class);

            if ($permission) {
                if ($permission->forbidden) {
                    $forbidden_level = 4;
                } else {
                    $allow_level = 3;
                }
            }
        }

        // Сравнение уровней доступа
        return $allow_level >= $forbidden_level;
    }

    /**
     * Allow user to perform an ability
     *
     * @param $team
     * @param string|array $ability
     * @param $entity
     * @param $target
     * @return bool
     */
    public function allowTeamAbility($team, string|array $ability, $entity, $target): bool
    {
        $entity_type = lcfirst(str_replace('App\Models\\', '', $entity::class));
        $abilityEdit  =  $entity_type.'s.edit';

        if (!$this->hasTeamAbility($team, $abilityEdit, $entity)) {
            return false;
        }

        // Get an ability to perform an action on specific entity object inside team
        $ability = Teams::abilityModel()::firstOrCreate(['name' => $ability, 'entity_id' => $entity->id, 'entity_type' => $entity::class, 'team_id' => $team->id]);

        if ($ability) {

            // Create a new permission for user entity
            $permission = Teams::permissionModel()::updateOrCreate(
                [
                    'team_id'     => $team->id,
                    'ability_id'  => $ability->id,
                    'entity_id'   => $target->id,
                    'entity_type' => get_class($target)
                ],
                [
                    'forbidden'   => 0
                ]
            );

            if ($permission) {
                return true;
            }
        }

        return false;
    }

    /**
     * Forbid user to perform an ability
     *
     * @param $team
     * @param string|array $ability
     * @param $entity
     * @param $target
     * @return bool
     */
    public function forbidTeamAbility($team, string|array $ability, $entity, $target): bool
    {
        $entity_type = lcfirst(str_replace('App\Models\\', '', $entity::class));
        $abilityEdit  =  $entity_type.'s.edit';

        if (!$this->hasTeamAbility($team, $abilityEdit, $entity)) {
            return false;
        }

        // Get an ability to perform an action on specific entity object inside team
        //
        $ability = Teams::abilityModel()::firstOrCreate(['name' => $ability, 'entity_id' => $entity->id, 'entity_type' => $entity::class, 'team_id' => $team->id]);

        if ($ability) {

            // Create a new permission for user entity
            //
            $permission = Teams::permissionModel()::updateOrCreate(
                [
                    'team_id'     => $team->id,
                    'ability_id'  => $ability->id,
                    'entity_id'   => $target->id,
                    'entity_type' => get_class($target)
                ],
                [
                    'forbidden'   => 1
                ]
            );

            if ($permission) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete user ability
     *
     * @param $team
     * @param string|array $ability
     * @param $entity
     * @param $target
     * @return bool
     */
    public function deleteTeamAbility($team, string|array $ability, $entity, $target): bool
    {
        $entity_type = lcfirst(str_replace('App\Models\\', '', $entity::class));
        $abilityEdit  =  $entity_type.'s.edit';

        if (!$this->hasTeamAbility($team, $abilityEdit, $entity)) {
            return false;
        }

        // Get an ability to perform an action on specific entity object inside team
        //
        $ability = Teams::abilityModel()::where(['name' => $ability, 'entity_id' => $entity->id, 'entity_type' => $entity::class, 'team_id' => $team->id])->first();

        if ($ability) {
            $permission = Teams::permissionModel()::where([
                'team_id'     => $team->id,
                'ability_id'  => $ability->id,
                'entity_id'   => $target->id,
                'entity_type' => get_class($target)])->first();

            if ($permission) {
                return $permission->delete();
            }

            return false;
        }

        return false;
    }
}
