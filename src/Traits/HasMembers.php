<?php

namespace Jurager\Teams\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
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
use Jurager\Teams\Events\TeamTransferred;
use Jurager\Teams\Events\TeamTransferring;
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
     * Query the assigned rules on the record, with their permissions and recipients.
     */
    public function rules(Model $record): HasMany
    {
        return $this->abilities()->whereMorphedTo('entity', $record)
            ->where(fn (Builder $query) => $query->has('users')->orHas('roles')->orHas('groups'))
            ->with(['permission', 'users', 'roles', 'groups']);
    }

    /**
     * Get the pending invitations of the team.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Teams::model('invitation'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Start adding one or more users to the team. Finish with as($role).
     *
     * @template TUsers of Model|iterable<Model>
     *
     * @param  TUsers  $users
     * @return PendingMember<TUsers>
     */
    public function add(Model|iterable $users): PendingMember
    {
        return new PendingMember($this, $users);
    }

    /**
     * Remove members from the team, along with their team groups and record rules.
     *
     * @param  Model|iterable<Model>  $users
     */
    public function remove(Model|iterable $users): void
    {
        $users = collect($users instanceof Model ? [$users] : $users)
            ->unique(fn (Model $user) => $user->getKey())->values();

        if ($users->isEmpty()) {
            return;
        }

        $this->getConnection()->transaction(function () use ($users) {
            $team = $this->lockForUpdate()->findOrFail($this->getKey());

            foreach ($users as $user) {
                if ($user->owns($team)) {
                    throw new RuntimeException('The team owner cannot be changed or removed.');
                }

                if (! $user->in($team)) {
                    throw new RuntimeException('User does not belong to the team.');
                }
            }

            foreach ($users as $user) {
                TeamMemberRemoving::dispatch($this, $user);

                $this->members()->detach($user);

                if ($this->members()->whereKey($user)->exists()) {
                    throw new RuntimeException('The membership could not be removed.');
                }

                $this->release($user);

                TeamMemberRemoved::dispatch($this, $user);
            }
        });
    }

    /**
     * Make the user the owner of the team.
     *
     * With a role, the previous owner stays in the team as a member with that role. Without one, they leave the team.
     */
    public function transfer(Model $user, string|BackedEnum|Model|null $role = null): void
    {
        $this->getConnection()->transaction(function () use ($user, $role) {

            $team = $this->lockForUpdate()->findOrFail($this->getKey());

            if ($user->owns($team)) {
                throw new RuntimeException('The user already owns the team.');
            }

            $role = $role instanceof BackedEnum || is_string($role) ? $this->role($role) : $role;
            $previous = $team->owner()->firstOrFail();

            if ($this->relationLoaded('owner')) {
                $this->owner?->unsetRelation('teams')->unsetRelation('memberships');
            }

            $this->setRawAttributes($team->getAttributes(), true)->unsetRelation('owner');

            TeamTransferring::dispatch($this, $user, $previous);

            $this->members()->detach($user);

            if ($this->members()->whereKey($user)->exists()) {
                throw new RuntimeException('The membership could not be removed.');
            }

            if (! $this->forceFill(['user_id' => $user->getKey()])->save()) {
                throw new RuntimeException('The team ownership could not be transferred.');
            }

            if ($role === null) {
                $this->release($previous);
            } else {
                $this->members()->attach($previous, ['role_id' => $role->getKey()]);

                if (! $this->members()->whereKey($previous)->exists()) {
                    throw new RuntimeException('The membership could not be created.');
                }
            }

            $this->unsetRelation('owner')->unsetRelation('members');
            $user->unsetRelation('teams')->unsetRelation('memberships');
            $previous->unsetRelation('teams')->unsetRelation('memberships');

            TeamTransferred::dispatch($this, $user, $previous);
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
    public function role(string|BackedEnum $code): Model
    {
        return $this->roles()->where('code', $code instanceof BackedEnum ? $code->value : $code)->firstOrFail();
    }

    /**
     * Find a team group by its code.
     */
    public function group(string|BackedEnum $code): Model
    {
        return $this->groups()->where('code', $code instanceof BackedEnum ? $code->value : $code)->firstOrFail();
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

            if ($this->delete() === false) {
                throw new RuntimeException('The team could not be deleted.');
            }
        });
    }

    /**
     * Remove the user's team groups and record rules once they have left the team.
     */
    protected function release(Model $user): void
    {
        $user->groups()->detach($this->groups()->pluck($this->groups()->getRelated()->getKeyName()));

        $rules = $user->abilities()->whereBelongsTo($this, 'team');
        $user->abilities()->detach($rules->pluck($rules->getRelated()->getQualifiedKeyName()));

        $this->unsetRelation('members');
        $user->unsetRelation('memberships')->unsetRelation('groups')->unsetRelation('abilities');
    }
}
