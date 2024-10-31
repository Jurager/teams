<?php

namespace Jurager\Teams\Contracts;

interface RemovesTeamMembers
{
    /**
     * Remove the team member from the given team.
     */
    public function remove(object $user, object $team, mixed $teamMember): void;
}
