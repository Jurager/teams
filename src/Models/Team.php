<?php

namespace Jurager\Teams\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Jurager\Teams\Owner;
use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Model;

abstract class Team extends Model
{

    protected $with = [
        'roles.capabilities',
        'groups'
    ];

    protected $appends = [
        'roles',
    ];

	/**
	 * Get the owner of the team.
	 *
	 * @return BelongsTo
	 */
	public function owner(): BelongsTo
	{
		return $this->belongsTo(Teams::userModel(), 'user_id');
	}

	/**
	 * Get all of the team's users including its owner.
	 *
	 * @return Collection
	 */
	public function allUsers(): Collection
	{
		return $this->users->merge([$this->owner]);
	}

	/**
	 * Get all of the users that belong to the team.
	 *
	 * @return BelongsToMany
	 */
	public function users(): BelongsToMany
	{
		return $this->belongsToMany(Teams::userModel(), Teams::membershipModel())
			->withPivot('role')
			->withTimestamps()
			->as('membership');
	}


	/**
	 * @return BelongsToMany
	 */
	public function abilities(): BelongsToMany
	{
		return $this->belongsToMany(Teams::abilityModel(), Teams::permissionModel())
			->withTimestamps()
			->withPivot(['entity_type', 'entity_id'])
			->as('permission');
	}

    /**
     * Get all roles of the team.
     *
     * @return HasMany
     */
	public function roles(): HasMany
	{
        return $this->hasMany(Teams::$roleModel);
    }

    /**
     * Get all groups of the team.
     *
     * @return HasMany
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Teams::$groupModel);
    }

    /**
     * Determine if Team has registered roles.
     *
     * @return bool
     */
    public function hasRoles(): bool
    {
        return count($this->roles) > 0;
    }

	/**
	 * @param string $name
	 * @param array $capabilities
	 * @return Model
	 */
    public function addRole(string $name, array $capabilities): Model
    {
        $role = $this->roles()->create(['name' => $name]);
        $role->capabilities()->attach($capabilities);
        return $role;
    }

    /**
     * @param string $name
     * @return Model
     */
    public function addGroup(string $name): Model
    {
        return $this->groups()->create(['name' => $name]);
    }

    /**
     * Find the role with the given id.
     *
     * @param  string  $id
     * @return Model
     */
    public function findRole(string $id): Model
    {
        return $this->roles->firstWhere('id', $id);
    }


    public function userRole($user)
    {
        if ($this->owner == $user) {
            return new Owner;
        }

        if (!$this->hasUser($user)) {
            return;
        }
        
        return $this->findRole($this->users->where( 'id', $user->id)->first()->membership->role);
    }

	/**
	 * Determine if the given user belongs to the team.
	 *
	 * @param User $user
	 * @return bool
	 */
	public function hasUser(User $user): bool
	{
		return $this->users->contains($user) || $user->ownsTeam($this);
	}

	/**
	 * Determine if the given email address belongs to a user on the team.
	 *
	 * @param  string  $email
	 * @return bool
	 */
	public function hasUserWithEmail(string $email): bool
	{
		return $this->allUsers()->contains(function ($user) use ($email) {
			return $user->email === $email;
		});
	}

	/**
	 * Determine if the given user has the given permission on the team.
	 *
	 * @param User $user
	 * @param string|array $permission
	 * @param bool $require
	 * @return bool
	 */
	public function userHasPermission($user, string|array $permission, bool $require = false): bool
	{
		return $user->hasTeamPermission($this, $permission, $require);
	}

	/**
	 * Get all of the pending user invitations for the team.
	 *
	 * @return HasMany
	 */
	public function invitations(): HasMany
	{
		return $this->hasMany(Teams::invitationModel());
	}

	/**
	 * Remove the given user from the team.
	 *
	 * @param User $user
	 * @return void
	 */
	public function removeUser(User $user): void
	{
		if ($user->{config('teams.foreign_keys.current_team_id', 'current_team_id')} === $this->id) {
			$user->forceFill([config('teams.foreign_keys.current_team_id', 'current_team_id') => null])->save();
		}

		$this->users()->detach($user);
	}

	/**
	 * Purge all of the team's resources.
	 *
	 * @return void
	 */
	public function purge()
	{
		$this->owner()->where(config('teams.foreign_keys.current_team_id', 'current_team_id'), $this->id)
			->update([config('teams.foreign_keys.current_team_id', 'current_team_id') => null]);

		$this->users()->where(config('teams.foreign_keys.current_team_id', 'current_team_id'), $this->id)
			->update([config('teams.foreign_keys.current_team_id', 'current_team_id') => null]);

		$this->users()->detach();

		$this->delete();
	}
}