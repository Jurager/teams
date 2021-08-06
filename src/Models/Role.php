<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Model;

abstract class Role extends Model
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'team_id', 'name', 'description', 'level'];

	public $timestamps = false;

	protected $with = [
	    'capabilities',
    ];

	protected $appends = [
	    'permissions'
    ];

	protected $hidden = [
	    'capabilities'
    ];

	/**
	 * Get the team that the invitation belongs to.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function team()
	{
		return $this->belongsTo(Teams::teamModel());
	}
	
	public function capabilities()
    {
        return $this->belongsToMany(Teams::$capabilityModel, 'role_capability');
    }

    public function getPermissionsAttribute()
    {
        return $this->capabilities->pluck('code')->toArray();
    }
}
