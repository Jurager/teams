<?php

namespace Jurager\Teams\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TeamMemberInvited extends TeamInvitationEvent implements ShouldDispatchAfterCommit
{
    //
}
