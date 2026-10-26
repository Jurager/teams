<?php

namespace Jurager\Teams\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TeamMemberAdded extends TeamMemberEvent implements ShouldDispatchAfterCommit
{
    //
}
