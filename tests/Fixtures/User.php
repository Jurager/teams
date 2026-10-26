<?php

namespace Jurager\Teams\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Jurager\Teams\Traits\HasTeams;

class User extends Authenticatable
{
    use HasFactory;
    use HasTeams;

    protected $guarded = [];

    public $timestamps = false;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
