<?php

namespace Jurager\Teams\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberAdding;
use RuntimeException;
use Throwable;

/**
 * @template TUsers of Model|iterable<Model>
 */
class PendingMember
{
    /**
     * @param  TUsers  $users
     */
    public function __construct(
        protected Model $team,
        protected Model|iterable $users,
    ) {}

    /**
     * Add the users to the team with the given role.
     *
     * @return (TUsers is Model ? Member : Collection<int, Member>)
     *
     * @throws Throwable
     */
    public function as(string|BackedEnum|Model $role): Member|Collection
    {
        $users = collect($this->users instanceof Model ? [$this->users] : $this->users)
            ->unique(fn (Model $user) => $user->getKey())->values();

        if ($users->isEmpty()) {
            return collect();
        }

        $this->team->getConnection()->transaction(function () use ($users, $role) {

            $team = $this->team->lockForUpdate()->findOrFail($this->team->getKey());

            foreach ($users as $user) {
                if ($user->in($team)) {
                    throw new RuntimeException('User already belongs to the team.');
                }
            }

            $role = $role instanceof Model ? $role : $this->team->role($role);

            foreach ($users as $user) {

                TeamMemberAdding::dispatch($this->team, $user);

                $this->team->members()->attach($user, ['role_id' => $role->getKey()]);

                if (! $this->team->members()->whereKey($user)->exists()) {
                    throw new RuntimeException('The membership could not be created.');
                }

                $this->team->unsetRelation('members');
                $user->unsetRelation('memberships');

                TeamMemberAdded::dispatch($this->team, $user);
            }
        });

        $members = $users->map(fn (Model $user): Member => $this->team->member($user));

        return $this->users instanceof Model ? $members->first() : $members;
    }
}
