<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jurager\Teams\Teams;

class Invitation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['email', 'role'];

    /**
     * Get the team that the invitation belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::$teamModel);
    }
}
