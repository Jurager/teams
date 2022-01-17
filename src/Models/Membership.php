<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Config;

abstract class Membership extends Pivot
{
    /**
     * The table associated with the pivot model.
     *
     * @var string
     */
    protected $table;

	protected $with = [
		'role'
	];

	/**
	 * @param array $attributes
	 */
	public function __construct(array $attributes = [])
    {
        $this->table = Config::get('teams.tables.team_user', 'team_user');
        parent::__construct($attributes);
    }

	/**
	 * @return BelongsTo
	 */
	public function role(): BelongsTo
    {
        return $this->belongsTo(Teams::$roleModel, 'role_id', 'id');
    }
}
