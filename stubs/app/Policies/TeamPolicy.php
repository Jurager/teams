<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
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
     */
    public function update(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can add team members.
     */
    public function addTeamMember(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can update team member
     */
    public function updateTeamMember(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can remove team members.
     */
    public function removeTeamMember(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }
}
