<?php

namespace Jurager\Teams\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamCreating;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamMemberRemoved;
use Jurager\Teams\Events\TeamMemberRemoving;
use Jurager\Teams\Events\TeamUpdated;
use Jurager\Teams\Events\TeamUpdating;
use Jurager\Teams\Support\Facades\Teams;
use Jurager\Teams\Support\Member;
use Jurager\Teams\Support\PendingInvitation;
use Jurager\Teams\Support\PendingMember;
use RuntimeException;

trait HasMembers
{
    /**
     * Dispatch the team lifecycle events.
     */
    public static function bootHasMembers(): void
    {
        static::creating(fn (Model $team) => TeamCreating::dispatch($team));
        static::created(fn (Model $team) => TeamCreated::dispatch($team));
        static::updating(fn (Model $team) => TeamUpdating::dispatch($team));
        static::updated(fn (Model $team) => TeamUpdated::dispatch($team));
        static::deleted(fn (Model $team) => TeamDeleted::dispatch($team));
    }

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
     * Get the pending invitations of the team.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Teams::model('invitation'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Start adding the user to the team. Finish with as($role).
     */
    public function add(Model $user): PendingMember
    {
        return new PendingMember($this, $user);
    }

    /**
     * Remove the member from the team, along with their team groups and record rules.
     */
    public function remove(Model $user): void
    {
        $this->getConnection()->transaction(function () use ($user) {
            $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());

            if ($user->owns($this)) {
                throw new RuntimeException('The team owner cannot be changed or removed.');
            }

            if (! $user->in($this)) {
                throw new RuntimeException('User does not belong to the team.');
            }

            TeamMemberRemoving::dispatch($this, $user);

            $user->groups()->detach($this->groups()->pluck($this->groups()->getRelated()->getKeyName()));

            $rules = $user->abilities()->whereBelongsTo($this, 'team');
            $user->abilities()->detach($rules->pluck($rules->getRelated()->getQualifiedKeyName()));

            $this->members()->detach($user);

            $this->unsetRelation('members');
            $user->unsetRelation('memberships')->unsetRelation('groups')->unsetRelation('abilities');

            TeamMemberRemoved::dispatch($this, $user);
        });
    }

    /**
     * Get the user's role and permissions in the team.
     */
    public function member(Model $user): Member
    {
        return new Member($this, $user);
    }

    /**
     * Find a team role by its code.
     */
    public function role(string $code): Model
    {
        return $this->roles()->where('code', $code)->firstOrFail();
    }

    /**
     * Find a team group by its code.
     */
    public function group(string $code): Model
    {
        return $this->groups()->where('code', $code)->firstOrFail();
    }

    /**
     * Start inviting the email to the team. Finish with as($role).
     */
    public function invite(string $email): PendingInvitation
    {
        if (! Config::get('teams.invitations.enabled')) {
            throw new RuntimeException('Team invitations are disabled.');
        }

        return new PendingInvitation($this, $email);
    }

    /**
     * Remove all members and delete the team.
     */
    public function purge(): void
    {
        $this->getConnection()->transaction(function () {
            $this->members()->detach();

            $this->delete();
        });
    }
}
