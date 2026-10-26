<?php

namespace Jurager\Teams\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ScopedUser extends User
{
    protected $table = 'users';

    public function groups(): BelongsToMany
    {
        return parent::groups()->where('groups.code', 'visible');
    }
}
