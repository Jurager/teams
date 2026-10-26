<?php

namespace Jurager\Teams\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamMemberRemoving
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The team instance.
     */
    public Model $team;

    /**
     * The team member being removed.
     */
    public Model $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Model $team, Model $user)
    {
        $this->team = $team;
        $this->user = $user;
    }
}
