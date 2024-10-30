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
     * Remove a member from the specified team.
     *
     * @param  mixed  $user       User initiating the removal
     * @param  mixed  $team       The team to remove the member from
     * @param  mixed  $teamMember The member to be removed
     * @return void
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function remove(mixed $user, mixed $team, mixed $teamMember): void
    {
        $this->authorize($user, $team, $teamMember);
        $this->ensureMemberIsNotTeamOwner($teamMember, $team);

        $team->deleteUser($teamMember);

        TeamMemberRemoved::dispatch($team, $teamMember);
    }

    /**
     * Authorize that the user can remove the specified team member.
     *
     * @param  mixed  $user       User initiating the removal
     * @param  mixed  $team       The team from which the member is being removed
     * @param  mixed  $teamMember The member being removed
     * @return void
     *
     * @throws AuthorizationException
     */
    protected function authorize(mixed $user, mixed $team, mixed $teamMember): void
    {
        if ($user->id !== $teamMember->id && !Gate::forUser($user)->check('removeTeamMember', $team)) {
            throw new AuthorizationException;
        }
    }

    /**
     * Ensure that the team member is not the owner of the team.
     *
     * @param  mixed  $teamMember The member being removed
     * @param  mixed  $team       The team to check ownership against
     * @return void
     *
     * @throws ValidationException
     */
    protected function ensureMemberIsNotTeamOwner(mixed $teamMember, mixed $team): void
    {
        if ($teamMember->id === $team->owner->id) {
            throw ValidationException::withMessages([
                'team' => [__('You may not remove the team owner.')],
            ])->errorBag('removeTeamMember');
        }
    }
}
