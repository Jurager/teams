<?php

namespace Jurager\Teams\Observers;

use Illuminate\Database\Eloquent\Model;

class GroupObserver
{
    public function deleting(Model $group): void
    {
        $group->permissions()->detach();
        $group->abilities()->detach();
    }
}
