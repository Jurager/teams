<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

class Ability extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['permission_id', 'title', 'entity_id', 'entity_type'];

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
        return $this->belongsTo(TeamsFacade::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get all the users that are assigned this ability.
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(TeamsFacade::model('user'), 'entity', Config::get('teams.tables.entity_ability'), 'ability_id', 'entity_id')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Get all the groups that are assigned this ability.
     */
    public function groups(): MorphToMany
    {
        return $this->morphedByMany(TeamsFacade::model('group'), 'entity', Config::get('teams.tables.entity_ability'), 'ability_id', 'entity_id')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Get all the roles that are assigned this ability.
     */
    public function roles(): MorphToMany
    {
        return $this->morphedByMany(TeamsFacade::model('role'), 'entity', Config::get('teams.tables.entity_ability'), 'ability_id', 'entity_id')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Get the record that the ability applies to.
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
