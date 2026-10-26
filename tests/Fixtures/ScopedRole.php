<?php

namespace Jurager\Teams\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Jurager\Teams\Models\Role;

class ScopedRole extends Role
{
    public function permissions(): MorphToMany
    {
        return parent::permissions()->where('permissions.code', 'visible.role');
    }
}
