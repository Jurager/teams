<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams;

/**
 * A permission on one record of a team, allowed or forbidden to users, roles and groups.
 */
class Ability extends Model
{
    use Prunable {
        prune as protected pruneModel;
    }

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

    /**
     * Get the users assigned the rule.
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(Teams::model('user'), 'entity', Config::get('teams.tables.entity_ability'), 'ability_id', 'entity_id')
            ->using(AbilityAssignment::class)
            ->withPivot('forbidden', 'expires_at')
            ->withTimestamps();
    }

    /**
     * Get the roles assigned the rule.
     */
    public function roles(): MorphToMany
    {
        return $this->morphedByMany(Teams::model('role'), 'entity', Config::get('teams.tables.entity_ability'), 'ability_id', 'entity_id')
            ->using(AbilityAssignment::class)
            ->withPivot('forbidden', 'expires_at')
            ->withTimestamps();
    }

    /**
     * Get the groups assigned the rule.
     */
    public function groups(): MorphToMany
    {
        return $this->morphedByMany(Teams::model('group'), 'entity', Config::get('teams.tables.entity_ability'), 'ability_id', 'entity_id')
            ->using(AbilityAssignment::class)
            ->withPivot('forbidden', 'expires_at')
            ->withTimestamps();
    }

    /**
     * Get the ability definitions without any assignments.
     */
    public function prunable(): Builder
    {
        $assignments = $this->getConnection()->table(Config::get('teams.tables.entity_ability'))
            ->whereColumn('ability_id', $this->getQualifiedKeyName());

        return static::query()->whereNotExists($assignments);
    }

    /**
     * Recheck assignments under the same locks used when writing rules.
     */
    public function prune(): ?bool
    {
        return $this->getConnection()->transaction(function () {

            if ($this->team()->lockForUpdate()->first() === null) {
                return false;
            }

            $ability = $this->withoutGlobalScopes()->lockForUpdate()->find($this->getKey());

            if ($ability === null) {
                return false;
            }

            $assigned = $this->getConnection()->table(Config::get('teams.tables.entity_ability'))
                ->where('ability_id', $this->getKey())->lockForUpdate()->first();

            return $assigned === null ? $ability->pruneModel() : false;
        });
    }
}
