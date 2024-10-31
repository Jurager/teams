<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     * @return mixed
     */
    public function viewAny(): mixed
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param object $user
     * @param object $team
     * @return mixed
     */
    public function view(object $user, object $team): mixed
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(object $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param object $user
     * @param object $team
     * @return mixed
     */
    public function update(object $user, object $team): mixed
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can add team members.
     *
     * @param object $user
     * @param object $team
     * @return mixed
     */
    public function addTeamMember(object $user, object $team): mixed
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can update team member permissions.
     *
     * @param object $user
     * @param object $team
     * @return mixed
     */
    public function updateTeamMember(object $user, object $team): mixed
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can remove team members.
     *
     * @param object $user
     * @param object $team
     * @return mixed
     */
    public function removeTeamMember(object $user, object $team): mixed
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param object $user
     * @param object $team
     * @return mixed
     */
    public function delete(object $user, object $team): mixed
    {
        return $user->ownsTeam($team);
    }
}