<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class Capability extends Model
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<string>
	 */
	protected $fillable = ['name', 'code' ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

	/**
	 * @return BelongsToMany
	 */
	public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$teamModel, 'role_capability');
    }
}