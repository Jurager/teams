<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

abstract class Permission extends Model
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'team_id', 'ability_id', 'entity_id', 'entity_type', 'forbidden'];

	/**
	 * @return BelongsTo
	 */
	public function team(): BelongsTo
	{
		return $this->belongsTo(Teams::teamModel());
	}

	/**
	 * @return BelongsTo
	 */
	public function ability(): BelongsTo
	{
		return $this->belongsTo(Teams::abilityModel());
	}
}
