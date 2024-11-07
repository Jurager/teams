<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Jurager\Teams\Support\Facades\Teams;

class Role extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['code', 'name', 'description'];

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
        'permissions',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'permissions',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->fillable[] = config('teams.foreign_keys.team_id');
    }

    /**
     * Get the team that the role belongs to.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::model('team'));
    }

    /**
     * Get the permissions that belongs to role.
     *
     * @return MorphMany
     */
    public function permissions(): MorphMany
    {
        return $this->morphMany(Teams::model('permission'), 'entity');
    }

    /**
     * Get the abilities that belongs to role.
     *
     * @return MorphMany
     */
    public function abilities(): MorphMany
    {
        return $this->morphMany(Teams::model('ability'), 'entity');
    }
}
