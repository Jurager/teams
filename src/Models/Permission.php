<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

class Permission extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'code'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

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
        return $this->table ?? Config::get('teams.tables.permissions', 'permissions');
    }

    /**
     * Get the team that the permission belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get all the groups that are assigned this permission.
     */
    public function groups(): MorphToMany
    {
        return $this->morphedByMany(TeamsFacade::model('group'), 'entity', Config::get('teams.tables.entity_permission'), 'permission_id', 'entity_id');
    }

    /**
     * Get all the roles that are assigned this permission.
     */
    public function roles(): MorphToMany
    {
        return $this->morphedByMany(TeamsFacade::model('role'), 'entity', Config::get('teams.tables.entity_permission'), 'permission_id', 'entity_id');
    }
}
