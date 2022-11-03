<?php

namespace App\Actions\Teams;

use Jurager\Teams\Mail\Invitation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Jurager\Teams\Contracts\InvitesTeamMembers;
use Jurager\Teams\Events\InvitingTeamMember;
use Jurager\Teams\Teams;
use Jurager\Teams\Rules\Role;

class InviteTeamMember implements InvitesTeamMembers
{
	/**
	 * Invite a new team member to the given team.
	 *
	 * @param mixed $user
	 * @param mixed $team
	 * @param string $email
	 * @param string|null $role
	 * @return void
	 * @throws \Illuminate\Auth\Access\AuthorizationException
	 */
	public function invite($user, $team, string $email, string $role = null)
	{
		Gate::forUser($user)->authorize('addTeamMember', $team);

		$this->validate($team, $email, $role);

		InvitingTeamMember::dispatch($team, $email, $role);

		$invitation = $team->invitations()->create([
			'email' => $email,
			'role'  => $role,
		]);

		Mail::to($email)->send(new Invitation($invitation));
	}

	/**
	 * Validate the invite member operation.
	 *
	 * @param mixed $team
	 * @param string $email
	 * @param string|null $role
	 * @return void
	 * @throws \Illuminate\Validation\ValidationException
	 */
	protected function validate($team, string $email, ?string $role)
	{
		Validator::make([
			'email' => $email,
			'role'  => $role,
		], $this->rules($team), [
			'email.unique' => __('This user has already been invited to the team.'),
		])->after(
			$this->ensureUserIsNotAlreadyOnTeam($team, $email)
		)->validateWithBag('addTeamMember');
	}

	/**
	 * Get the validation rules for inviting a team member.
	 *
	 * @param  mixed  $team
	 * @return array
	 */
	protected function rules($team)
	{
		return array_filter([
			'email' => ['required', 'email', Rule::unique(config('teams.tables.invitations', 'invitations'))->where(function ($query) use ($team) {
				$query->where(config('teams.foreign_keys.team_id', 'team_id'), $team->id);
			})],
			'role' => Teams::hasRoles()
				? ['required', 'string', new Role($team)]
				: null,
		]);
	}

	/**
	 * Ensure that the user is not already on the team.
	 *
	 * @param  mixed  $team
	 * @param  string  $email
	 * @return \Closure
	 */
	protected function ensureUserIsNotAlreadyOnTeam($team, string $email)
	{
		return function ($validator) use ($team, $email) {
			$validator->errors()->addIf(
				$team->hasUserWithEmail($email),
				'email',
				__('This user already belongs to the team.')
			);
		};
	}
}