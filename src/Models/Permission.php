<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jurager\Teams\Teams;

class Permission extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['team_id', 'ability_id', 'entity_id', 'entity_type', 'forbidden'];

    /**
     * Get the team that the permission belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::$teamModel);
    }

    /**
     * Get the ability that the permission belongs to.
     */
    public function ability(): BelongsTo
    {
        return $this->belongsTo(Teams::$abilityModel);
    }
}
