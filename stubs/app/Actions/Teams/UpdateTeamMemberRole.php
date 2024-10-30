<?php

namespace App\Actions\Teams;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Rules\Role;
use Jurager\Teams\Teams;

class UpdateTeamMemberRole
{
    /**
     * Update the role for the given team member.
     *
     * @param  mixed  $user
     * @param  mixed  $team
     * @param  int  $teamMemberId
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update($user, $team, $teamMemberId, string $role)
    {
        Gate::forUser($user)->authorize('updateTeamMember', $team);

        $this->ensureUserDoesNotOwnTeam($teamMemberId, $team);

        Validator::make([
            'role' => $role,
        ], [
            'role' => ['required', 'string', new Role],
        ])->validate();

        $team->users()->updateExistingPivot($teamMemberId, ['role' => $role]);

        TeamMemberUpdated::dispatch($team->fresh(), Teams::findUserByIdOrFail($teamMemberId));
    }

    /**
     * Ensure that the team member is not the owner of the team.
     *
     * @param  int  $teamMemberId The id member being removed
     * @param  mixed  $team       The team to check ownership against
     * @return void
     *
     * @throws ValidationException
     */
    protected function ensureMemberIsNotTeamOwner(int $teamMemberId, mixed $team): void
    {
        if ($teamMemberId === $team->owner->id) {
            throw ValidationException::withMessages([
                'team' => [__('You may not remove the team owner.')],
            ])->errorBag('removeTeamMember');
        }
    }
}
