<?php

namespace Jurager\Teams\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TeamMemberUpdated extends TeamMemberEvent implements ShouldDispatchAfterCommit
{
    //
}
