<?php

namespace App\Models;

use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Model;

abstract class Group extends Model
{

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
