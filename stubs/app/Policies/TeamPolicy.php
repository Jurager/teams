<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     * @return bool
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param object $user
     * @param object $team
     * @return bool
     */
    public function view(object $user, object $team): bool
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
     * @return bool
     */
    public function update(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can add team members.
     *
     * @param object $user
     * @param object $team
     * @return bool
     */
    public function addTeamMember(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can update team member
     *
     * @param object $user
     * @param object $team
     * @return bool
     */
    public function updateTeamMember(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can remove team members.
     *
     * @param object $user
     * @param object $team
     * @return bool
     */
    public function removeTeamMember(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param object $user
     * @param object $team
     * @return bool
     */
    public function delete(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }
}