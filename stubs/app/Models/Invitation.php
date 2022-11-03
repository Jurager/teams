<?php

namespace App\Models;

use Jurager\Teams\Teams;
use Jurager\Teams\Models\Invitation as InvitationModel;

class Invitation extends InvitationModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'email', 'role' ];

    /**
     * Get the team that the invitation belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Teams::teamModel());
    }
}