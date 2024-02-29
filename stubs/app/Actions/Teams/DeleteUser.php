<?php

namespace App\Actions\Teams;

use Illuminate\Support\Facades\DB;
use Jurager\Teams\Contracts\DeletesTeams;
use Jurager\Teams\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * The team deleter implementation.
     */
    protected DeletesTeams $deletesTeams;

    /**
     * Create a new action instance.
     *
     * @return void
     */
    public function __construct(DeletesTeams $deletesTeams)
    {
        $this->deletesTeams = $deletesTeams;
    }

    /**
     * Delete the given user.
     */
    public function delete(mixed $user): void
    {
        DB::transaction(function () use ($user) {
            $this->deleteTeams($user);
            $user->delete();
        });
    }

    /**
     * Delete the teams and team associations attached to the user.
     */
    protected function deleteTeams(mixed $user): void
    {
        $user->teams()->detach();

        $user->ownedTeams->each(function ($team) {
            $this->deletesTeams->delete($team);
        });
    }
}
