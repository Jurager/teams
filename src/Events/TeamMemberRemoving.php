<?php

namespace Jurager\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TeamMemberRemoving
{
    use Dispatchable;

    /**
     * The team instance.
     */
    public mixed $team;

    /**
     * The team member being removed.
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
