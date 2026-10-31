<?php

namespace Jurager\Teams\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Facades\Teams;

/** Eloquent relationships of a team, also usable on independent team models. */
trait HasTeamRelations
{
    /**
     * Get the owner of the team.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Teams::model('user'), 'user_id');
    }

    /**
     * Get the team members, without the owner.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('user'), Teams::model('membership'), Config::get('teams.foreign_keys.team_id'), 'user_id')
            ->withPivot('role_id')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Get the team roles.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Teams::model('role'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get the team groups.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Teams::model('group'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get the team permissions.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Teams::model('permission'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get the team record abilities.
     */
    public function abilities(): HasMany
    {
        return $this->hasMany(Teams::model('ability'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Query the assigned rules on the record, with their permissions and recipients.
     */
    public function rules(Model|string $record, int|string|null $key = null): HasMany
    {
        return $this->abilities()->forRecord($record, $key)
            ->with(['permission', 'recipient']);
    }

    /**
     * Get the pending invitations of the team.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Teams::model('invitation'), Config::get('teams.foreign_keys.team_id'));
    }
}
