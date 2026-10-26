<?php

namespace Jurager\Teams\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TeamCreated extends TeamEvent implements ShouldDispatchAfterCommit
{
    //
}
