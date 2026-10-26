<?php

namespace Jurager\Teams\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Jurager\Teams\Traits\HasTeams;

class User extends Authenticatable
{
    use HasTeams;

    protected $guarded = [];

    public $timestamps = false;
}
