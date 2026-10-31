<?php

namespace Jurager\Teams;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Jurager\Teams\Events\TeamMemberRemoved;
use Jurager\Teams\Events\TeamMemberRemoving;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Events\TeamTransferred;
use Jurager\Teams\Events\TeamTransferring;
use RuntimeException;

/** Changes membership and moves users between member and owner status. */
class TeamMembers
{
    public function __construct(protected Model $team) {}

    /**
     * Change the member's role.
     */
    public function assign(Model $user, string|BackedEnum|Model $role): void
    {
        $this->team->getConnection()->transaction(function () use ($user, $role) {

            $team = $this->team->lockForUpdate()->findOrFail($this->team->getKey());

            if ($user->owns($team)) {
                throw new RuntimeException('The team owner cannot be changed or removed.');
            }

            if (! $user->in($team)) {
                throw new RuntimeException('User does not belong to the team.');
            }

            $role = $role instanceof Model ? $role : $this->team->role($role);

            $this->team->members()->updateExistingPivot($user->getKey(), ['role_id' => $role->getKey()]);

            if (! $this->team->members()->whereKey($user)->wherePivot('role_id', $role->getKey())->exists()) {
                throw new RuntimeException('The membership could not be updated.');
            }

            $this->team->unsetRelation('members');
            $user->unsetRelation('memberships');

            TeamMemberUpdated::dispatch($this->team, $user);
        });
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

        $this->team->getConnection()->transaction(function () use ($users) {
            $team = $this->team->lockForUpdate()->findOrFail($this->team->getKey());

            foreach ($users as $user) {
                if ($user->owns($team)) {
                    throw new RuntimeException('The team owner cannot be changed or removed.');
                }

                if (! $user->in($team)) {
                    throw new RuntimeException('User does not belong to the team.');
                }
            }

            foreach ($users as $user) {
                TeamMemberRemoving::dispatch($this->team, $user);

                $this->team->members()->detach($user);

                if ($this->team->members()->whereKey($user)->exists()) {
                    throw new RuntimeException('The membership could not be removed.');
                }

                $this->release($user);

                TeamMemberRemoved::dispatch($this->team, $user);
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
        $this->team->getConnection()->transaction(function () use ($user, $role) {

            $team = $this->team->lockForUpdate()->findOrFail($this->team->getKey());

            if ($user->owns($team)) {
                throw new RuntimeException('The user already owns the team.');
            }

            $role = $role instanceof BackedEnum || is_string($role) ? $this->team->role($role) : $role;
            $previous = $team->owner()->firstOrFail();

            if ($this->team->relationLoaded('owner')) {
                $this->team->owner?->unsetRelation('teams')->unsetRelation('memberships');
            }

            $this->team->setRawAttributes($team->getAttributes(), true)->unsetRelation('owner');

            TeamTransferring::dispatch($this->team, $user, $previous);

            $this->team->members()->detach($user);

            if ($this->team->members()->whereKey($user)->exists()) {
                throw new RuntimeException('The membership could not be removed.');
            }

            if (! $this->team->forceFill(['user_id' => $user->getKey()])->save()) {
                throw new RuntimeException('The team ownership could not be transferred.');
            }

            if ($role === null) {
                $this->release($previous);
            } else {
                $this->team->members()->attach($previous, ['role_id' => $role->getKey()]);

                if (! $this->team->members()->whereKey($previous)->exists()) {
                    throw new RuntimeException('The membership could not be created.');
                }
            }

            $this->team->unsetRelation('owner')->unsetRelation('members');
            $user->unsetRelation('teams')->unsetRelation('memberships');
            $previous->unsetRelation('teams')->unsetRelation('memberships');

            TeamTransferred::dispatch($this->team, $user, $previous);
        });
    }

    /**
     * Remove all members and delete the team.
     */
    public function purge(): void
    {
        $this->team->getConnection()->transaction(function () {
            $this->team->members()->detach();

            if ($this->team->delete() === false) {
                throw new RuntimeException('The team could not be deleted.');
            }
        });
    }

    /**
     * Remove the user's team groups and record rules once they have left the team.
     */
    protected function release(Model $user): void
    {
        $user->groups()->detach($this->team->groups()->pluck($this->team->groups()->getRelated()->getKeyName()));

        $user->abilities()->whereBelongsTo($this->team, 'team')->delete();

        $this->team->unsetRelation('members');
        $user->unsetRelation('memberships')->unsetRelation('groups')->unsetRelation('abilities');
    }
}
