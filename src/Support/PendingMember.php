<?php

namespace Jurager\Teams\Support;

use Illuminate\Database\Eloquent\Model;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberAdding;
use RuntimeException;

class PendingMember
{
    public function __construct(
        protected Model $team,
        protected Model $user,
    ) {}

    /**
     * Add the user to the team with the given role.
     */
    public function as(string|Model $role): Member
    {
        $this->team->getConnection()->transaction(function () use ($role) {

            $this->team->newQuery()->lockForUpdate()->findOrFail($this->team->getKey());

            if ($this->user->in($this->team)) {
                throw new RuntimeException('User already belongs to the team.');
            }

            $role = $role instanceof Model ? $role : $this->team->role($role);

            TeamMemberAdding::dispatch($this->team, $this->user);

            $this->team->members()->attach($this->user, ['role_id' => $role->getKey()]);

            $this->team->unsetRelation('members');
            $this->user->unsetRelation('memberships');

            TeamMemberAdded::dispatch($this->team, $this->user);
        });

        return $this->team->member($this->user);
    }
}
