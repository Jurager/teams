<?php

namespace Jurager\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TeamMemberUpdated
{
    use Dispatchable;

    /**
     * The team instance.
     */
    public mixed $team;

    /**
     * The team member that was updated.
     */
    public mixed $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(mixed $team, mixed $user)
    {
        $this->team = $team;
        $this->user = $user;
    }
}
