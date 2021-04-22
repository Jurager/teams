<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;

abstract class Team extends Model
{
    /**
     * Get the owner of the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo(Teams::userModel(), 'user_id');
    }

    /**
     * Get all of the team's users including its owner.
     *
     * @return \Illuminate\Support\Collection
     */
    public function allUsers()
    {
        return $this->users->merge([$this->owner]);
    }

    /**
     * Get all of the users that belong to the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(Teams::userModel(), Teams::membershipModel())
			->withPivot('role')
			->withTimestamps()
			->as('membership');
    }

    /**
     * Determine if the given user belongs to the team.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function hasUser($user)
    {
        return $this->users->contains($user) || $user->ownsTeam($this);
    }

    /**
     * Determine if the given email address belongs to a user on the team.
     *
     * @param  string  $email
     * @return bool
     */
    public function hasUserWithEmail(string $email)
    {
        return $this->allUsers()->contains(function ($user) use ($email) {
            return $user->email === $email;
        });
    }

    /**
     * Determine if the given user has the given permission on the team.
     *
     * @param  \App\Models\User  $user
     * @param  string  $permission
     * @return bool
     */
    public function userHasPermission($user, $permission)
    {
        return $user->hasTeamPermission($this, $permission);
    }

    /**
     * Get all of the pending user invitations for the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invitations()
    {
        return $this->hasMany(Teams::invitationModel());
    }

    /**
     * Remove the given user from the team.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function removeUser($user)
    {
        if ($user->{config('teams.foreign_keys.current_team_id', 'current_team_id')} === $this->id) {
            $user->forceFill([
	            config('teams.foreign_keys.current_team_id', 'current_team_id') => null,
            ])->save();
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
