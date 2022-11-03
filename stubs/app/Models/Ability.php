<?php

namespace App\Models;

use Jurager\Teams\Teams;
use Jurager\Teams\Models\Ability as AbilityModel;

class Ability extends AbilityModel
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'team_id', 'name', 'title', 'entity_id', 'entity_type', 'only_owned', 'options' ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
	{
		return $this->belongsTo(Teams::teamModel());
	}
}