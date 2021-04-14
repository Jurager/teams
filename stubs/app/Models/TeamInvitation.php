<?php

namespace App\Models;

use Jurager\Teams\Teams;
use Jurager\Teams\TeamInvitation as ModelTeamInvitation;

class TeamInvitation extends ModelTeamInvitation
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'role',
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
}