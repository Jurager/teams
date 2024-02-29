<?php

namespace Jurager\Teams\Contracts;

interface RemovesTeamMembers
{
    /**
     * Remove the team member from the given team.
     */
    public function remove(mixed $user, mixed $team, mixed $teamMember): void;
}
