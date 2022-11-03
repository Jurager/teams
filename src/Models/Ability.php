<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Model;

abstract class Ability extends Model
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'team_id', 'name', 'title', 'entity_id', 'entity_type', 'only_owned', 'options' ];

	/**
	 * Get the team that the ability belongs to.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
	public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
		return $this->belongsTo(Teams::teamModel());
	}
}
