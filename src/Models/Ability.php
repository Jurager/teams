<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Ability extends Model
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<string>
	 */
	protected $fillable = [ 'team_id', 'name', 'title', 'entity_id', 'entity_type' ];

	/**
	 * Get the team that the ability belongs to.
	 *
	 * @return BelongsTo
     */
	public function team(): BelongsTo
    {
		return $this->belongsTo(Teams::$teamModel);
	}
}
