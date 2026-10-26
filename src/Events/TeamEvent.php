<?php

namespace Jurager\Teams\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class TeamEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The team instance.
     */
    public Model $team;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Model $team)
    {
        $this->team = $team;
    }
}
