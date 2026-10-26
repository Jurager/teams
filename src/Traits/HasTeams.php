<?php

namespace Jurager\Teams\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams;

trait HasTeams
{
    /**
     * Get the teams the user owns.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Teams::model('team'), 'user_id');
    }

    /**
     * Get the teams the user is a member of.
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('team'), Teams::model('membership'), 'user_id', Config::get('teams.foreign_keys.team_id'))
            ->withPivot('role_id')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Get the groups the user is in.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('group'), Config::get('teams.tables.group_user'), 'user_id', 'group_id');
    }

    /**
     * Get the user's own record rules.
     */
    public function abilities(): MorphToMany
    {
        return $this->morphToMany(Teams::model('ability'), 'entity', Config::get('teams.tables.entity_ability'), 'entity_id', 'ability_id')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Determine if the user owns the team.
     */
    public function owns(Model $team): bool
    {
        return $this->getKey() !== null && $team->user_id !== null
            && (string) $this->getKey() === (string) $team->user_id;
    }

    /**
     * Determine if the user owns the team or is one of its members.
     */
    public function in(Model $team): bool
    {
        return $this->owns($team) || $this->memberships()->whereKey($team->getKey())->exists();
    }
}
