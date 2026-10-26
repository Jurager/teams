<?php

namespace Jurager\Teams\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Jurager\Teams\Traits\HasMembers;

class Workspace extends Model
{
    use HasMembers;

    protected $table = 'workspaces';

    protected $fillable = ['user_id', 'name'];
}
