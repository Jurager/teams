<?php

namespace Jurager\Teams\Contracts;

interface AddsTeamMembers
{
    /**
     * Add a new team member to the given team.
     */
    public function add(object $user, object $team, string $email, ?string $role = null): void;
}
