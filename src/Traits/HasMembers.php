<?php

namespace Jurager\Teams\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamCreating;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamUpdated;
use Jurager\Teams\Events\TeamUpdating;
use Jurager\Teams\Member;
use Jurager\Teams\Pending;
use Jurager\Teams\TeamMembers;
use RuntimeException;

trait HasMembers
{
    use HasTeamRelations;

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
     * Start adding one or more users to the team. Finish with as($role).
     *
     * @template TUsers of Model|iterable<Model>
     *
     * @param  TUsers  $users
     * @return Pending\Membership<TUsers>
     */
    public function add(Model|iterable $users): Pending\Membership
    {
        return new Pending\Membership($this, $users);
    }

    /** @param Model|iterable<Model> $users */
    public function remove(Model|iterable $users): void
    {
        (new TeamMembers($this))->remove($users);
    }

    /** Keep the previous owner as a member by supplying a role. */
    public function transfer(Model $user, string|BackedEnum|Model|null $role = null): void
    {
        (new TeamMembers($this))->transfer($user, $role);
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
    public function invite(string $email): Pending\Invitation
    {
        if (! Config::get('teams.invitations.enabled')) {
            throw new RuntimeException('Team invitations are disabled.');
        }

        return new Pending\Invitation($this, $email);
    }

    public function purge(): void
    {
        (new TeamMembers($this))->purge();
    }
}
