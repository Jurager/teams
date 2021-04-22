<?php

namespace App\Actions\Teams;

use Jurager\Teams\Contracts\DeletesTeams;

class DeleteTeam implements DeletesTeams
{
	/**
	 * Delete the given team.
	 *
	 * @param  mixed  $team
	 * @return void
	 */
	public function delete($team)
	{
		$team->purge();
	}
}