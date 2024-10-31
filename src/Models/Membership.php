<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Jurager\Teams\Teams;

class Membership extends Pivot
{
    /**
     * The table associated with the pivot model.
     *
     * @var string
     */
    protected $table;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [
        'role',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = config('teams.tables.team_user', 'team_user');

        parent::__construct($attributes);
    }

    /**
     * Get the role that the membership belongs to.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Teams::role(), 'role_id', 'id');
    }
}
