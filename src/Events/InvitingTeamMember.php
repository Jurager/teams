<?php

namespace Jurager\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;

class InvitingTeamMember
{
    use Dispatchable;

    /**
     * The team instance.
     *
     * @var mixed
     */
    public mixed $team;

    /**
     * The email address of the invitee.
     *
     * @var mixed
     */
    public mixed $email;

    /**
     * The role of the invitee.
     *
     * @var mixed
     */
    public mixed $role;

    /**
     * Create a new event instance.
     *
     * @param  mixed  $team
     * @param  mixed  $email
     * @param  mixed  $role
     * @return void
     */
    public function __construct(mixed $team, mixed $email, mixed $role)
    {
        $this->team  = $team;
        $this->email = $email;
        $this->role  = $role;
    }
}
