<?php

namespace Jurager\Teams\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Facades\Teams;

trait HasTeams
{
    /**
     * Query users granted a team-wide permission by ownership, their role, or a team group.
     */
    public function scopePermission(Builder $query, string|BackedEnum $permission, Model $team): Builder
    {
        $teamId = $team->getKey();

        if ($teamId === null) {
            return $query->whereRaw('1 = 0');
        }

        $codes = Teams::model('permission')::codes($permission);
        $roles = $team->roles();
        $roleKey = $roles->getRelated()->getQualifiedKeyName();
        $pivotRole = $this->memberships()->qualifyPivotColumn('role_id');

        $hasPermission = fn (Builder $query): Builder => $query->whereIn('code', $codes);

        $hasMembership = fn (Builder $query, HasMany $roles): Builder => $query
            ->whereKey($teamId)
            ->whereIn($pivotRole, $roles->select($roleKey));

        $grantedRoles = (clone $roles)->whereHas('permissions', $hasPermission);

        $viaGroup = fn (Builder $query): Builder => $query
            ->whereHas('memberships', fn (Builder $query) => $hasMembership($query, clone $roles))
            ->whereHas('groups', fn (Builder $query) => $query
                ->whereBelongsTo($team, 'team')
                ->whereHas('permissions', $hasPermission));

        return $query->where(fn (Builder $query) => $query
            ->whereKey($team->user_id)
            ->orWhereHas('memberships', fn (Builder $query) => $hasMembership($query, $grantedRoles))
            ->orWhere($viaGroup));
    }

    /**
     * Query the teams the user owns or belongs to.
     */
    public function accessible(): Builder
    {
        $memberships = $this->memberships();

        return $memberships->getRelated()->newQuery()->where(fn (Builder $query) => $query
            ->whereBelongsTo($this, 'owner')
            ->orWhereIn($query->getModel()->getQualifiedKeyName(), $memberships
                ->select($memberships->getRelated()->getQualifiedKeyName())));
    }

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
     * Get the pending invitations sent to the user's email.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Teams::model('invitation'), 'email', 'email');
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
    public function abilities(): MorphMany
    {
        return $this->morphMany(Teams::model('ability'), 'recipient');
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
