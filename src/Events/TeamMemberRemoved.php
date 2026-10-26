<?php

namespace Jurager\Teams\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TeamMemberRemoved extends TeamMemberEvent implements ShouldDispatchAfterCommit
{
    //
}
