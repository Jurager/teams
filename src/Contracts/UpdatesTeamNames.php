<?php

namespace Jurager\Teams\Contracts;

interface UpdatesTeamNames
{
    /**
     * Validate and update the given team's name.
     */
    public function update(object $user, object $team, array $input): void;
}
