<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Jurager\Teams\Teams;

class Role extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['team_id', 'name', 'description'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [
        'capabilities',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'permissions',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'capabilities',
    ];

    /**
     * Get the team that the role belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::$teamModel);
    }

    /**
     * Get the capabilities that belongs to team.
     */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$capabilityModel, 'role_capability');
    }

    /**
     * Get the permissions of all team capabilities.
     */
    public function getPermissionsAttribute(): array
    {
        return $this->capabilities->pluck('code')->all();
    }
}
