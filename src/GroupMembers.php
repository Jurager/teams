<?php

namespace Jurager\Teams;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Events\GroupMembersUpdated;

/** Changes a group's members and reports actual assignments after commit. */
class GroupMembers
{
    public function __construct(protected Model $group) {}

    public function add(Collection $users): void
    {
        $this->group->getConnection()->transaction(function () use ($users) {
            $eligible = $this->eligible($users);

            $this->group->lockForUpdate()->findOrFail($this->group->getKey());

            $changes = $this->group->members()->syncWithoutDetaching($eligible->modelKeys());
            $this->changed($users, $changes['attached'], []);
        });
    }

    public function remove(Collection $users): void
    {
        $this->group->getConnection()->transaction(function () use ($users) {
            $this->group->lockForUpdate()->findOrFail($this->group->getKey());

            $members = $this->group->members();
            $ids = $members->whereKey($users->modelKeys())->pluck($members->getRelated()->getQualifiedKeyName())->all();

            $this->group->members()->detach($ids);
            $this->changed($users, [], $ids);
        });
    }

    /** Keep the team's owner and members; global groups accept anyone. */
    protected function eligible(Collection $users): Collection
    {
        if ($this->group->getAttribute(Config::get('teams.foreign_keys.team_id')) === null) {
            return $users;
        }

        $team = $this->group->team()->lockForUpdate()->firstOrFail();
        $members = $team->members();

        $ids = $members->whereKey($users->modelKeys())
            ->pluck($members->getRelated()->getQualifiedKeyName())
            ->push($team->user_id);

        return $users->whereIn($members->getRelated()->getKeyName(), $ids);
    }

    /**
     * @param  list<int|string>  $attached
     * @param  list<int|string>  $detached
     */
    protected function changed(Collection $users, array $attached, array $detached): void
    {
        $users->each->unsetRelation('groups');
        $this->group->unsetRelation('members');

        if ($attached !== [] || $detached !== []) {
            GroupMembersUpdated::dispatch($this->group, $attached, $detached);
        }
    }
}
