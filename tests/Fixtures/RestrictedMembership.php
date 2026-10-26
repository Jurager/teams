<?php

namespace Jurager\Teams\Tests\Fixtures;

use Jurager\Teams\Models\Membership;
use RuntimeException;

class RestrictedMembership extends Membership
{
    protected static function booted(): void
    {
        static::saving(function (Membership $membership) {
            if ($membership->role()->where('code', 'blocked')->exists()) {
                throw new RuntimeException('Blocked role.');
            }
        });
    }
}
