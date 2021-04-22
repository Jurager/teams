<?php

namespace App\Actions\Teams;

use Illuminate\Support\Facades\DB;
use Jurager\Teams\Contracts\DeletesTeams;
use Jurager\Teams\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
	/**
	 * The team deleter implementation.
	 *
	 * @var \Jurager\Teams\Contracts\DeletesTeams
	 */
	protected DeletesTeams $deletesTeams;

	/**
	 * Create a new action instance.
	 *
	 * @param  \Jurager\Teams\Contracts\DeletesTeams  $deletesTeams
	 * @return void
	 */
	public function __construct(DeletesTeams $deletesTeams)
	{
		$this->deletesTeams = $deletesTeams;
	}

	/**
	 * Delete the given user.
	 *
	 * @param  mixed  $user
	 * @return void
	 */
	public function delete($user)
	{
		DB::transaction(function () use ($user) {
			$this->deleteTeams($user);
			$user->deleteProfilePhoto();
			$user->tokens->each->delete();
			$user->delete();
		});
	}

	/**
	 * Delete the teams and team associations attached to the user.
	 *
	 * @param  mixed  $user
	 * @return void
	 */
	protected function deleteTeams($user)
	{
		$user->teams()->detach();

		$user->ownedTeams->each(function ($team) {
			$this->deletesTeams->delete($team);
		});
	}
}
