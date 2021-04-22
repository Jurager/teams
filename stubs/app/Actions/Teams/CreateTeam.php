<?php

namespace App\Actions\Teams;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Jurager\Teams\Contracts\CreatesTeams;
use Jurager\Teams\Events\AddingTeam;
use Jurager\Teams\Teams;

class CreateTeam implements CreatesTeams
{
	/**
	 * Validate and create a new team for the given user.
	 *
	 * @param mixed $user
	 * @param array $input
	 * @return mixed
	 * @throws \Illuminate\Auth\Access\AuthorizationException
	 * @throws \Illuminate\Validation\ValidationException
	 */
	public function create($user, array $input)
	{
		Gate::forUser($user)->authorize('create', Teams::newTeamModel());

		Validator::make($input, [
			'name' => ['required', 'string', 'max:255'],
		])->validateWithBag('createTeam');

		AddingTeam::dispatch($user);

		$user->switchTeam($team = $user->ownedTeams()->create([
			'name' => $input['name']
		]));

		return $team;
	}
}