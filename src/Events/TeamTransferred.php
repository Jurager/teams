<?php

namespace Jurager\Teams\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class TeamTransferred extends TeamTransferEvent implements ShouldDispatchAfterCommit
{
    //
}
