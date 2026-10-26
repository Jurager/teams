<?php

namespace Jurager\Teams\Tests\Fixtures;

use Jurager\Teams\Models\Team;

class CustomTeam extends Team
{
    protected static function booted(): void {}
}
