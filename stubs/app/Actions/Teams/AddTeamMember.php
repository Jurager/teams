<?php

namespace App\Actions\Teams;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Jurager\Teams\Contracts\AddsTeamMembers;
use Jurager\Teams\Events\AddingTeamMember;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Rules\Role;
use Jurager\Teams\Teams;

class AddTeamMember implements AddsTeamMembers
{
	/**
	 * Add a new team member to the given team.
	 *
	 * @param mixed $user
	 * @param mixed $team
	 * @param string $email
	 * @param string|null $role
	 * @return void
	 * @throws \Illuminate\Auth\Access\AuthorizationException
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function add($user, $team, string $email, string $role = null)
	{
		Gate::forUser($user)->authorize('addTeamMember', $team);

		$this->validate($team, $email, $role);

		$member = Teams::findUserByEmailOrFail($email);

		AddingTeamMember::dispatch($team, $member);

		$team->users()->attach( $member, ['role' => $role] );

		TeamMemberAdded::dispatch($team, $member);
	}

	/**
	 * Validate the add member operation.
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
			'role' => $role,
		], $this->rules(), [
			'email.exists' => __('We were unable to find a registered user with this email address.'),
		])->after(
			$this->ensureUserIsNotAlreadyOnTeam($team, $email)
		)->validateWithBag('addTeamMember');
	}

	/**
	 * Get the validation rules for adding a team member.
	 *
	 * @return array
	 */
	protected function rules()
	{
		return array_filter([
			'email' => ['required', 'email', 'exists:users'],
			'role' => Teams::hasRoles()
				? ['required', 'string', new Role]
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