<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

abstract class Capability extends Model
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = ['name', 'code' ];

    public $timestamps = false;

	/**
	 * @return BelongsToMany
	 */
	public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Teams::teamModel(), 'role_capability');
    }
}