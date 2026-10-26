<?php

namespace Jurager\Teams\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TeamDeleted extends TeamEvent implements ShouldDispatchAfterCommit
{
    //
}
