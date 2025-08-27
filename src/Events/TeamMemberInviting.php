<?php

namespace Jurager\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TeamMemberInviting
{
    use Dispatchable;

    /**
     * The team instance.
     */
    public mixed $team;

    /**
     * The email address of the invitee.
     */
    public mixed $email;

    /**
     * The role of the invitee.
     */
    public mixed $role;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(mixed $team, mixed $email, mixed $role)
    {
        $this->team = $team;
        $this->email = $email;
        $this->role = $role;
    }
}
