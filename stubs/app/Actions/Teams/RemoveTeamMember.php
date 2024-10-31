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
     * @param  object  $user       User initiating the removal
     * @param  object  $team       The team to remove the member from
     * @param  mixed  $teamMember The member to be removed
     * @return void
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function remove(object $user, object $team, mixed $teamMember): void
    {
        $this->authorize($user, $team, $teamMember);
        $this->ensureMemberIsNotTeamOwner($teamMember, $team);

        $team->deleteUser($teamMember);

        TeamMemberRemoved::dispatch($team, $teamMember);
    }

    /**
     * Authorize that the user can remove the specified team member.
     *
     * @param  object  $user       User initiating the removal
     * @param  object  $team       The team from which the member is being removed
     * @param  object  $teamMember The member being removed
     * @return void
     *
     * @throws AuthorizationException
     */
    protected function authorize(mixed $user, mixed $team, object $teamMember): void
    {
        if ($user->id !== $teamMember->id && !Gate::forUser($user)->check('removeTeamMember', $team)) {
            throw new AuthorizationException;
        }
    }

    /**
     * Ensure that the team member is not the owner of the team.
     *
     * @param  object  $teamMember The member being removed
     * @param  object  $team       The team to check ownership against
     * @return void
     *
     * @throws ValidationException
     */
    protected function ensureMemberIsNotTeamOwner(object $teamMember, object $team): void
    {
        if ($teamMember->id === $team->owner->id) {
            throw ValidationException::withMessages([
                'team' => [__('You may not remove the team owner.')],
            ])->errorBag('removeTeamMember');
        }
    }
}
