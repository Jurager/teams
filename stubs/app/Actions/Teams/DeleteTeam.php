<?php

namespace App\Actions\Teams;

use Jurager\Teams\Contracts\DeletesTeams;

class DeleteTeam implements DeletesTeams
{
    /**
     * Delete the given team.
     */
    public function delete(mixed $team): void
    {
        $team->purge();
    }
}
