<?php

namespace Jurager\Teams\Models;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

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

    /**
     * Creates a new instance of the model.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = Config::get('teams.tables.team_user', 'team_user');
    }

    /**
     * Get the role that the membership belongs to.
     *
     * @throws Exception
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('role'), 'role_id', 'id');
    }
}
