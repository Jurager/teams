<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

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
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'permissions',
    ];

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
        return $this->table ?? Config::get('teams.tables.roles', 'roles');
    }

    /**
     * Get the team that the role belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get all users associated with the role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(TeamsFacade::model('user'), TeamsFacade::model('membership'), 'role_id', 'user_id');
    }

    /**
     * Get all the permissions assigned to the role.
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(TeamsFacade::model('permission'), 'entity', Config::get('teams.tables.entity_permission'), 'entity_id', 'permission_id');
    }

    /**
     * Get all the abilities assigned to the role.
     */
    public function abilities(): MorphToMany
    {
        return $this->morphToMany(TeamsFacade::model('ability'), 'entity', Config::get('teams.tables.entity_ability'), 'entity_id', 'ability_id')
            ->withPivot('forbidden')
            ->withTimestamps();
    }
}
