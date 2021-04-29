<?php

namespace App\Actions\Teams;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Teams;
use Jurager\Teams\Rules\Role;

class UpdateTeamMemberRole
{
	/**
	 * Update the role for the given team member.
	 *
	 * @param mixed $user
	 * @param mixed $team
	 * @param int $teamMemberId
	 * @param string $role
	 * @return void
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

		$team->users()->updateExistingPivot($teamMemberId, [ 'role' => $role ]);

		TeamMemberUpdated::dispatch($team->fresh(), Teams::findUserByIdOrFail($teamMemberId));
	}

	/**
	 * Ensure that the currently authenticated user does not own the team.
	 *
	 * @param mixed $teamMemberId
	 * @param mixed $team
	 * @return void
	 * @throws ValidationException
	 */
	protected function ensureUserDoesNotOwnTeam($teamMemberId, $team)
	{
		if ($teamMemberId === $team->owner->id) {
			throw ValidationException::withMessages([
				'team' => [__('You may not change the role of the owner')],
			])->errorBag('updateTeamMemberRole');
		}
	}
}