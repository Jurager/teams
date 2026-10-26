<?php

namespace Jurager\Teams\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TeamUpdated extends TeamEvent implements ShouldDispatchAfterCommit
{
    //
}
