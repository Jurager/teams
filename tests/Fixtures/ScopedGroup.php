<?php

namespace Jurager\Teams\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Jurager\Teams\Models\Group;

class ScopedGroup extends Group
{
    public function permissions(): MorphToMany
    {
        return parent::permissions()->where('permissions.code', 'visible.group');
    }
}
