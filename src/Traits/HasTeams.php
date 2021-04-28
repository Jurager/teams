<?php

namespace Jurager\Teams\Traits;

use Jurager\Teams\Owner;
use Jurager\Teams\Teams;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

trait HasTeams
{
    /**
     * Determine if the given team is the current team.
     *
     * @param  $team
     * @return bool
     */
    public function isCurrentTeam($team): bool
    {
        return $team->id === $this->currentTeam->id;
    }

    /**
     * Get the current team of the user's context.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currentTeam(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
         return $this->belongsTo(Teams::teamModel(), config('teams.foreign_keys.current_team_id', 'current_team_id'));
    }

    /**
     * Switch the user's context to the given team.
     *
     * @param  $team
     * @return bool
     */
    public function switchTeam($team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->forceFill([ config('teams.foreign_keys.current_team_id', 'current_team_id') => $team->id ])->save();

        $this->setRelation('currentTeam', $team);

        return true;
    }

    /**
     * Get all of the teams the user owns or belongs to.
     *
     * @return \Illuminate\Support\Collection
     */
    public function allTeams(): \Illuminate\Support\Collection
    {
        return $this->ownedTeams->merge($this->teams)->sortBy('name');
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ownedTeams(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Teams::teamModel());
    }

    /**
     * Get all of the teams the user belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function teams()
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
        return $this->id == $team->{$this->getForeignKey()};
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
	 * @return Owner|\Jurager\Teams\Role|void|null
	 */
    public function teamRole($team)
    {
        if ($this->ownsTeam($team)) {
            return new Owner;
        }

        if (! $this->belongsToTeam($team)) {
            return;
        }

        return Teams::findRole($team->users->where(
            'id', $this->id
        )->first()->membership->role);
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

        return $this->belongsToTeam($team) && optional(Teams::findRole($team->users->where(
            'id', $this->id
        )->first()->membership->role))->key === $role;
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

        //if (in_array(HasApiTokens::class, class_uses_recursive($this)) &&
        //    ! $this->tokenCan($permission) &&
        //    $this->currentAccessToken() !== null) {
        //    return false;
        //}

        $permissions = $this->teamPermissions($team);

        return in_array($permission, $permissions) ||
               in_array('*', $permissions) ||
               (Str::endsWith($permission, ':create') && in_array('*:create', $permissions)) ||
               (Str::endsWith($permission, ':update') && in_array('*:update', $permissions));
    }
}
