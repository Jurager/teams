<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jurager\Teams\Teams;

class Ability extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['team_id', 'name', 'title', 'entity_id', 'entity_type'];

    /**
     * Get the team that the ability belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::$teamModel);
    }
}
