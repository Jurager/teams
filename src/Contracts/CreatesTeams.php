<?php

namespace Jurager\Teams\Contracts;

interface CreatesTeams
{
    /**
     * Validate and create a new team for the given user.
     *
     * @param  mixed  $user
     * @param  array  $input
     * @return mixed
     */
    public function create(mixed $user, array $input): mixed;
}
