<?php

namespace Jurager\Teams\Tests\Fixtures;

use Jurager\Teams\Models\Role;

class CustomRole extends Role
{
    protected static function booted(): void {}
}
