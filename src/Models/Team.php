<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Owner;
use Jurager\Teams\Teams;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;

abstract class Team extends Model
{

    protected $with = [
        'roles.capabilities',
        'groups'
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
	 * Get all the team's users including its owner.
	 *
	 * @return Collection
	 */
	public function allUsers(): Collection
	{
		return $this->users->merge([$this->owner]);
	}

	/**
	 * Get all the users that belong to the team.
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
	 * Get all the abilities belong to the team.
	 *
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
		// Create the new role
	    //
        $role = $this->roles()->create(['name' => $name]);

		// Array of capability id's
	    //
        $capability_ids = [];

        foreach ($capabilities as $capability) {

			// Get or create the new capability
	        //
            $item = (Teams::$capabilityModel)::firstOrCreate(['code' => $capability]);

			// Add the capability id for attaching
	        //
	        array_push($capability_ids, $item->id);
        }

		// Attach the capabilities to the role
	    //
        $role->capabilities()->attach($capability_ids);

		// Return the resulting role
	    //
        return $role;
    }

	/**
	 * @param string $name
	 * @param array $capabilities
	 * @return bool|Model|HasMany
	 */
    public function updateRole(string $name, array $capabilities): Model|HasMany|bool
    {
	    // Get the role
	    //
        $role = $this->roles()->firstWhere('name', $name);

	    // If found the role
	    //
        if ($role) {

	        // Array of capability id's
	        //
            $capability_ids = [];

            foreach ($capabilities as $capability) {

	            // Get or create the new capability
	            //
                $item = (Teams::$capabilityModel)::firstOrCreate(['code' => $capability]);

	            // Add the capability id for attaching
	            //
	            array_push($capability_ids, $item->id);
            }

	        // Sync the capabilities to the role
	        //
            $role->capabilities()->sync($capability_ids);

	        // Return the resulting role
	        //
	        return $role;
        }

        return false;
    }

    /**
     * Deletes the given role from team
     *
     * @param string $name
     * @return bool
     */
    public function deleteRole(string $name): bool
    {
        $role = $this->roles()->firstWhere('name', $name);

        if ($role) {
            return $this->roles()->delete($role);
        }

        return false;
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
	 * Delete group from the team
	 *
	 * @param string $name
	 * @return Model|bool
	 */
    public function deleteGroup(string $name): Model|bool
    {
        $group = $this->groups->firstWhere('name', $name);

        if ($group) {
            return $this->groups()->delete($group);
        }

        return false;
    }

	/**
	 * Find the role with the given id.
	 *
	 * @param string $id
	 * @return Model|bool
	 */
    public function findRole(string $id): Model|bool
    {
	    // Return the resulting role
	    //
        return $this->roles->firstWhere('id', $id) ?? false;
    }


	/**
	 * @param $user
	 * @return Model|Owner|bool
	 */
	public function userRole($user): Model|Owner|bool
	{
	    // If user is owner, return the owner model object
	    //
        if ($this->owner == $user) {
            return new Owner;
        }

	    // If team doesn't have such user
	    //
        if (!$this->hasUser($user)) {
            return false;
        }

		// Return the resulting role
		//
	    return $this->findRole($this->users->where( 'id', $user->id)->first()->membership->role) ?? false;
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
	 * Get all the pending user invitations for the team.
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
		if ($user->{Config::get('teams.foreign_keys.current_team_id', 'current_team_id')} === $this->id) {
			$user->forceFill([Config::get('teams.foreign_keys.current_team_id', 'current_team_id') => null])->save();
		}

		$this->users()->detach($user);
	}

	/**
	 * Purge all the team's resources.
	 *
	 * @return void
	 */
	public function purge()
	{
		$this->owner()->where(Config::get('teams.foreign_keys.current_team_id', 'current_team_id'), $this->id)
			->update([Config::get('teams.foreign_keys.current_team_id', 'current_team_id') => null]);

		$this->users()->where(Config::get('teams.foreign_keys.current_team_id', 'current_team_id'), $this->id)
			->update([Config::get('teams.foreign_keys.current_team_id', 'current_team_id') => null]);

		$this->users()->detach();

		$this->delete();
	}
}