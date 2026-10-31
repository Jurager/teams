<?php

namespace Jurager\Teams;

use BackedEnum;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Member
{
    protected MemberPermissions $permissions;

    public function __construct(
        public readonly Model $team,
        public readonly Model $user,
    ) {
        $this->permissions = new MemberPermissions($this);
    }

    /** Load a snapshot for record checks; role() and team-wide permissions remain live. */
    public function load(iterable $records): static
    {
        $this->permissions->load($records);

        return $this;
    }

    /**
     * Get the member's role: a team role, the owner role, or null for a non-member.
     */
    public function role(): Model|Owner|null
    {
        if ($this->user->owns($this->team)) {
            return new Owner;
        }

        $roles = $this->team->roles();
        $memberships = $this->user->memberships();

        return $roles->whereIn($roles->getRelated()->getQualifiedKeyName(), $memberships->whereKey($this->team->getKey())
            ->select($memberships->qualifyPivotColumn('role_id')))->first();
    }

    /**
     * Determine if the member has any of the given roles. The owner has every role.
     */
    public function is(string|BackedEnum|Model ...$roles): bool
    {
        $role = $this->role();

        if ($role instanceof Owner) {
            return true;
        }

        return $role !== null && collect($roles)->contains(
            fn ($expected) => match (true) {
                $expected instanceof Model => $expected->is($role),
                $expected instanceof BackedEnum => $expected->value === $role->code,
                default => $expected === $role->code,
            }
        );
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->permissions->codes();
    }

    public function can(string|BackedEnum|array $permissions, Model|string|null $record = null, int|string|null $key = null): bool
    {
        return $this->permissions->check(Arr::wrap($permissions), $record, all: true, key: $key)->allowed();
    }

    public function canAny(array $permissions, Model|string|null $record = null, int|string|null $key = null): bool
    {
        return $this->permissions->check($permissions, $record, all: false, key: $key)->allowed();
    }

    public function inspect(string|BackedEnum $permission, Model|string|null $record = null, int|string|null $key = null): Response
    {
        return $this->permissions->check([$permission], $record, all: true, key: $key);
    }

    public function assign(string|BackedEnum|Model $role): static
    {
        (new TeamMembers($this->team))->assign($this->user, $role);

        return $this;
    }

    /**
     * Start a rule that allows the member the permission on one record.
     */
    public function allow(string|BackedEnum $permission): Pending\Ability
    {
        return new Pending\Ability($this->team, $this->user, $permission, forbidden: false);
    }

    /**
     * Start a rule that forbids the member the permission on one record.
     */
    public function forbid(string|BackedEnum $permission): Pending\Ability
    {
        return new Pending\Ability($this->team, $this->user, $permission, forbidden: true);
    }

    /**
     * Start removing the member's own rule for the permission on one record.
     */
    public function forget(string|BackedEnum $permission): Pending\Ability
    {
        return new Pending\Ability($this->team, $this->user, $permission, forbidden: null);
    }
}
