<?php

namespace Jurager\Teams\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TeamMemberAdded
{
    use Dispatchable;

    /**
     * The team instance.
     *
     * @var mixed
     */
    public mixed $team;

    /**
     * The team member that was added.
     *
     * @var mixed
     */
    public mixed $user;

    /**
     * Create a new event instance.
     *
     * @param  mixed  $team
     * @param  mixed  $user
     * @return void
     */
    public function __construct(mixed $team, mixed $user)
    {
        $this->team = $team;
        $this->user = $user;
    }
}
