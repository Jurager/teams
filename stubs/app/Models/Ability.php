<?php

namespace App\Models;

use Jurager\Teams\Teams;
use Jurager\Teams\Models\Ability as AbilityInvitation;

class Ability extends AbilityInvitation
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'team_id', 'name', 'title', 'entity_id', 'entity_type', 'only_owned', 'options' ];

	/**
	 * Get the team that the invitation belongs to.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function team()
	{
		return $this->belongsTo(Teams::teamModel());
	}
}