<?php

namespace Jurager\Teams\Contracts;

interface DeletesTeams
{
    /**
     * Delete the given team.
     */
    public function delete(mixed $team): void;
}
