<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'team_id', 'name', 'description', 'level'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
	protected $with = [
	    'capabilities',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
	    'permissions'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
	    'capabilities'
    ];

	/**
	 * Get the team that the invitation belongs to.
	 *
	 * @return BelongsTo
	 */
	public function team(): BelongsTo
	{
		return $this->belongsTo(Teams::$teamModel);
	}

	/**
	 * @return BelongsToMany
	 */
	public function capabilities(): BelongsToMany
	{
        return $this->belongsToMany(Teams::$capabilityModel, 'role_capability');
    }

	/**
	 * @return array
	 */
	public function getPermissionsAttribute(): array
	{
        return $this->capabilities->pluck('code')->all();
    }
}