<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams;

/**
 * A permission on one record of a team, allowed or forbidden to users, roles and groups.
 */
class Ability extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['permission_id', 'entity_id', 'entity_type'];

    /**
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->mergeFillable([Config::get('teams.foreign_keys.team_id')]);

        parent::__construct($attributes);
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->table ?? Config::get('teams.tables.abilities', 'abilities');
    }

    /**
     * Get the team that the ability belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get the permission that the ability applies.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Teams::model('permission'), 'permission_id');
    }

    /**
     * Get the record that the ability applies to.
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
