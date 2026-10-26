<?php

namespace Jurager\Teams\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamMemberUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The team instance.
     */
    public Model $team;

    /**
     * The team member that was updated.
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
