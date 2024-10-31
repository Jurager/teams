<?php

namespace Jurager\Teams\Contracts;

interface CreatesTeams
{
    /**
     * Validate and create a new team for the given user.
     */
    public function create(object $user, array $input): mixed;
}
