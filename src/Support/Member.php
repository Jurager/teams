<?php

namespace Jurager\Teams\Support;

use BackedEnum;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Models\Owner;
use Jurager\Teams\Support\Facades\Teams;
use RuntimeException;

class Member
{
    /**
     * The role, groups, and record rules loaded by load(), shared by the checks on those records.
     *
     * @var array{role: Model|null, groups: array{0: Collection, 1: Collection}, records: array<string, int>, rules: array<string, Collection>}|null
     */
    protected ?array $loaded = null;

    public function __construct(
        public readonly Model $team,
        public readonly Model $user,
    ) {}

    /**
     * Load the member's role, groups, and rules on the given records at once, so checks on these records run no queries.
     *
     * The loaded data is not refreshed, so load it right before the checks.
     */
    public function load(iterable $records): static
    {
        if ($this->user->owns($this->team)) {
            return $this;
        }

        $records = collect($records)
            ->filter(fn (Model $record): bool => $this->contains($record));

        [$global, $groups] = $this->groups();

        $role = $this->role()?->load('permissions');

        $rules = [
            'user' => collect(),
            'groups' => collect(),
            'role' => collect(),
        ];

        if ($records->isNotEmpty() && $role !== null) {

            $constraint = $this->constraint($records);

            $rules = [
                'user' => $constraint($this->user->abilities())->get(),
                'groups' => $groups->load(['abilities' => $constraint])->flatMap->abilities,
                'role' => $role->load(['abilities' => $constraint])->abilities,
            ];
        }

        $this->loaded = [
            'role' => $role,
            'groups' => [$global, $groups],
            'records' => $records
                ->map(fn (Model $record) => $this->key($record))
                ->flip()
                ->all(),
            'rules' => $rules,
        ];

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

    /**
     * Get the permission codes granted by the member's role and team groups.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        if ($this->user->owns($this->team)) {
            return ['*'];
        }

        return $this->permissionSources()->flatten()->unique()->values()->all();
    }

    /**
     * Determine if the member has all of the given permissions, on the team or on one of its records.
     */
    public function can(string|BackedEnum|array $permissions, ?Model $record = null): bool
    {
        return $this->check(Arr::wrap($permissions), $record, all: true)->allowed();
    }

    /**
     * Determine if the member has any of the given permissions, on the team or on one of its records.
     */
    public function canAny(array $permissions, ?Model $record = null): bool
    {
        return $this->check($permissions, $record, all: false)->allowed();
    }

    /**
     * Inspect the permission and explain which rule decides access.
     */
    public function inspect(string|BackedEnum $permission, ?Model $record = null): Response
    {
        return $this->check([$permission], $record, all: true);
    }

    /**
     * Change the member's role.
     */
    public function assign(string|BackedEnum|Model $role): static
    {
        $this->team->getConnection()->transaction(function () use ($role) {

            $team = $this->team->lockForUpdate()->findOrFail($this->team->getKey());

            if ($this->user->owns($team)) {
                throw new RuntimeException('The team owner cannot be changed or removed.');
            }

            if (! $this->user->in($team)) {
                throw new RuntimeException('User does not belong to the team.');
            }

            $role = $role instanceof Model ? $role : $this->team->role($role);

            $this->team->members()->updateExistingPivot($this->user->getKey(), ['role_id' => $role->getKey()]);

            if (! $this->team->members()->whereKey($this->user)->wherePivot('role_id', $role->getKey())->exists()) {
                throw new RuntimeException('The membership could not be updated.');
            }

            $this->team->unsetRelation('members');
            $this->user->unsetRelation('memberships');

            TeamMemberUpdated::dispatch($this->team, $this->user);
        });

        return $this;
    }

    /**
     * Start a rule that allows the member the permission on one record.
     */
    public function allow(string|BackedEnum $permission): PendingRule
    {
        return new PendingRule($this->team, $this->user, $permission, forbidden: false);
    }

    /**
     * Start a rule that forbids the member the permission on one record.
     */
    public function forbid(string|BackedEnum $permission): PendingRule
    {
        return new PendingRule($this->team, $this->user, $permission, forbidden: true);
    }

    /**
     * Start removing the member's own rule for the permission on one record.
     */
    public function forget(string|BackedEnum $permission): PendingRule
    {
        return new PendingRule($this->team, $this->user, $permission, forbidden: null);
    }

    /**
     * Check all, or any, of the permissions.
     *
     * @param  list<string|BackedEnum>  $permissions
     */
    protected function check(array $permissions, ?Model $record, bool $all): Response
    {
        if ($permissions === []) {
            return Response::deny(__('No permission was requested.'), 'missing_permission');
        }

        if ($record !== null && ! $this->contains($record)) {
            return Response::deny(__('The record belongs to another team.'), 'team_mismatch');
        }

        if ($this->user->owns($this->team)) {
            return Response::allow(__('The user owns the team.'), 'team_owner');
        }

        if ($record === null) {
            $sources = $this->permissionSources();

            if ($sources->isEmpty()) {
                return Response::deny(__('The user does not belong to the team.'), 'not_member');
            }

            $roles = $sources->get('role', collect());
            $groups = $sources->get('group', collect());
        }

        foreach ($permissions as $permission) {
            if ($permission instanceof BackedEnum) {
                $permission = (string) $permission->value;
            }

            $response = $record !== null
                ? $this->allows($permission, $record)
                : $this->decide(collect(), $roles, $permission, 'role')
                ?? $this->decide(collect(), $groups, $permission, 'group')
                ?? Response::deny(__('No permission grants access.'), 'missing_permission');

            if ($response->allowed() !== $all) {
                return $response;
            }
        }

        return $response;
    }

    /**
     * Determine if the member may use the permission on the record.
     */
    protected function allows(string $permission, Model $record): Response
    {
        if (method_exists($record, 'isOwner') && $record->isOwner($this->user)) {
            return Response::allow(__('The user owns the record.'), 'record_owner');
        }

        [$globalGroups, $teamGroups] = $this->loaded['groups'] ?? $this->groups();

        if ($this->matches($globalGroups->flatMap->permissions->pluck('code')->all(), $permission)) {
            return Response::allow(__('Granted by a global group.'), 'global_group');
        }

        $role = $this->loaded === null ? $this->role() : $this->loaded['role'];

        if ($role === null) {
            return Response::deny(__('The user does not belong to the team.'), 'not_member');
        }

        $rules = $this->rules($record, $permission, $teamGroups, $role);

        // The member's own rules decide first, then the rules of their team groups, then of their role.
        return $this->decide($rules('user'), [], $permission, 'user')
            ?? $this->decide($rules('groups'), $teamGroups->flatMap->permissions->pluck('code')->all(), $permission, 'group')
            ?? $this->decide($rules('role'), $role->permissions->pluck('code')->all(), $permission, 'role')
            ?? Response::deny(__('No permission grants access.'), 'missing_permission');
    }

    /**
     * Get the team-wide permission codes by their source. Non-members have no sources.
     *
     * @return Collection<string, list<string>>
     */
    protected function permissionSources(): Collection
    {
        $role = $this->role();

        if ($role === null) {
            return collect();
        }

        $groups = $this->user->groups()->whereBelongsTo($this->team, 'team')->with('permissions')->get();

        return collect([
            'role' => $role->permissions->pluck('code')->all(),
            'group' => $groups->flatMap->permissions->pluck('code')->all(),
        ]);
    }

    /**
     * Get the user's global groups and their groups in the team, with permissions.
     *
     * @return array{0: Collection, 1: Collection}
     */
    protected function groups(): array
    {
        $teamKey = Config::get('teams.foreign_keys.team_id');

        [$global, $team] = $this->user->groups()
            ->where(fn ($query) => $query->whereBelongsTo($this->team, 'team')->orWhereNull($teamKey))
            ->with('permissions')
            ->get()
            ->partition(fn ($group) => $group->getAttribute($teamKey) === null);

        return [$global, $team];
    }

    /**
     * Get a resolver of the rules on the record that cover the permission, for the user, their groups, or their role.
     */
    protected function rules(Model $record, string $permission, Collection $groups, Model $role): Closure
    {
        $key = $this->key($record);
        $codes = Teams::model('permission')::codes($permission);

        if (isset($this->loaded['records'][$key])) {
            return fn (string $level) => $this->loaded['rules'][$level]->filter(
                fn (Model $rule): bool => "{$rule->entity_type}:{$rule->entity_id}" === $key && in_array($rule->permission->code, $codes, true)
            );
        }

        $constraint = $this->constraint(collect([$record]), $codes);

        return fn (string $level) => match ($level) {
            'user' => $constraint($this->user->abilities())->get(),
            'groups' => $groups->load(['abilities' => $constraint])->flatMap->abilities,
            'role' => $role->load(['abilities' => $constraint])->abilities,
        };
    }

    /**
     * Build a constraint for the team's rules on the records. With codes, only the rules for those codes are kept.
     *
     * @param  list<string>|null  $codes
     */
    protected function constraint(Collection $records, ?array $codes = null): Closure
    {
        $entities = $records
            ->groupBy(fn (Model $record): string => $record->getMorphClass())
            ->map(fn (Collection $group): array => $group->map->getKey()->all());

        return function ($query) use ($entities, $codes) {

            $query->whereBelongsTo($this->team, 'team');

            $query->where(static function ($query) use ($entities): void {
                foreach ($entities as $type => $ids) {
                    $query->orWhere(static fn ($query) => $query
                        ->where($query->qualifyColumn('entity_type'), $type)
                        ->whereIn($query->qualifyColumn('entity_id'), $ids));
                }
            });

            if ($codes === null) {
                return $query->with('permission');
            }

            $permissions = $this->team->permissions();
            $permission = $permissions->getRelated();

            return $query->whereIn(
                $query->qualifyColumn('permission_id'),
                $permissions
                    ->whereIn($permission->qualifyColumn('code'), $codes)
                    ->select($permission->getQualifiedKeyName()),
            );
        };
    }

    /**
     * Get the key that identifies the record among records of any type.
     */
    protected function key(Model $record): string
    {
        return $record->getMorphClass().':'.$record->getKey();
    }

    /**
     * Decide from one level of rules: a forbid denies, an allow or a granted permission passes.
     *
     * Returns null when this level has nothing to say, so the next level decides.
     *
     * @param  list<string>  $granted
     */
    protected function decide(Collection $rules, array $granted, string $permission, string $source): ?Response
    {
        $rules = $rules->filter(fn (Model $rule): bool => $rule->pivot->expires_at?->isFuture() ?? true);

        if ($rules->contains(fn ($rule) => $rule->pivot->forbidden)) {
            return Response::deny(match ($source) {
                'user' => __('Forbidden by a personal rule.'),
                'group' => __('Forbidden by a group rule.'),
                'role' => __('Forbidden by a role rule.'),
            }, "{$source}_forbidden");
        }

        if ($rules->isNotEmpty()) {
            return Response::allow(match ($source) {
                'user' => __('Allowed by a personal rule.'),
                'group' => __('Allowed by a group rule.'),
                'role' => __('Allowed by a role rule.'),
            }, "{$source}_allowed");
        }

        if ($this->matches($granted, $permission)) {
            return Response::allow($source === 'group' ? __('Granted by a group.') : __('Granted by the role.'), "{$source}_permission");
        }

        return null;
    }

    /**
     * Determine if the record belongs to the team.
     */
    protected function contains(Model $record): bool
    {
        $teamKey = Config::get('teams.foreign_keys.team_id');

        return isset($record->getAttributes()[$teamKey])
            && $this->team->getKey() !== null
            && (string) $record->getAttribute($teamKey) === (string) $this->team->getKey();
    }

    /**
     * Determine if any of the granted codes covers the permission.
     *
     * @param  list<string>  $granted
     */
    protected function matches(array $granted, string $permission): bool
    {
        return array_intersect(Teams::model('permission')::codes($permission), $granted) !== [];
    }
}
