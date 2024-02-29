<?php

namespace Jurager\Teams\Contracts;

interface UpdatesTeamNames
{
    /**
     * Validate and update the given team's name.
     */
    public function update(mixed $user, mixed $team, array $input): void;
}
