<?php

namespace Jurager\Teams\Contracts;

interface AddsTeamMembers
{
	/**
	 * Add a new team member to the given team.
	 *
	 * @param mixed $user
	 * @param mixed $team
	 * @param string $email
	 * @param string|null $role
	 * @return void
	 */
    public function add(mixed $user, mixed $team, string $email, string|null $role = null): void;
}
