<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Jurager\Teams\Teams;

abstract class Membership extends Pivot
{
    /**
     * The table associated with the pivot model.
     *
     * @var string
     */
    protected $table;

    public function __construct(array $attributes = [])
    {
        $this->table = config('teams.tables.team_user', 'team_user');
        parent::__construct($attributes);
    }

    protected $with = [
        'role'
    ];

    public function role()
    {
        return $this->belongsTo(Teams::$roleModel, 'role_id', 'id');
    }
}
