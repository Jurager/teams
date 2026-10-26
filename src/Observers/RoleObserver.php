<?php

namespace Jurager\Teams\Observers;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class RoleObserver
{
    public function deleting(Model $role): void
    {
        if ($role->users()->exists()) {
            throw new RuntimeException('Reassign or remove team members before deleting their role.');
        }

        $role->permissions()->detach();
        $role->abilities()->detach();
    }
}
