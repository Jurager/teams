<?php

namespace App\Actions\Teams;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Jurager\Teams\Contracts\RemovesTeamMembers;
use Jurager\Teams\Events\TeamMemberRemoved;

class RemoveTeamMember implements RemovesTeamMembers
{
    /**
     * Remove the team member from the given team.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function remove(mixed $user, mixed $team, mixed $teamMember): void
    {
        $this->authorize($user, $team, $teamMember);

        $this->ensureUserDoesNotOwnTeam($teamMember, $team);

        $team->deleteUser($teamMember);

        TeamMemberRemoved::dispatch($team, $teamMember);
    }

    /**
     * Authorize that the user can remove the team member.
     *
     * @param  mixed  $user
     * @param  mixed  $team
     * @param  mixed  $teamMember
     * @return void
     *
     * @throws AuthorizationException
     */
    protected function authorize($user, $team, $teamMember)
    {
        if (! Gate::forUser($user)->check('removeTeamMember', $team) &&
            $user->id !== $teamMember->id) {
            throw new AuthorizationException;
        }
    }

    /**
     * Ensure that the currently authenticated user does not own the team.
     *
     * @param  mixed  $teamMember
     * @param  mixed  $team
     * @return void
     *
     * @throws ValidationException
     */
    protected function ensureUserDoesNotOwnTeam($teamMember, $team)
    {
        if ($teamMember->id === $team->owner->id) {
            throw ValidationException::withMessages([
                'team' => [__('You may not leave a team that you created.')],
            ])->errorBag('removeTeamMember');
        }
    }
}
