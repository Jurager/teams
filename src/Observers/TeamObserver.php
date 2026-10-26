<?php

namespace Jurager\Teams\Observers;

use Illuminate\Database\Eloquent\Model;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamCreating;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamUpdated;
use Jurager\Teams\Events\TeamUpdating;

class TeamObserver
{
    public function creating(Model $team): void
    {
        TeamCreating::dispatch($team);
    }

    public function created(Model $team): void
    {
        TeamCreated::dispatch($team);
    }

    public function updating(Model $team): void
    {
        TeamUpdating::dispatch($team);
    }

    public function updated(Model $team): void
    {
        TeamUpdated::dispatch($team);
    }

    public function deleted(Model $team): void
    {
        TeamDeleted::dispatch($team);
    }
}
