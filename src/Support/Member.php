<?php

namespace Jurager\Teams\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Models\Owner;
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
    public function is(string|Model ...$roles): bool
    {
        $role = $this->role();

        if ($role instanceof Owner) {
            return true;
        }

        return $role !== null && collect($roles)->contains(
            fn ($expected) => $expected instanceof Model ? $expected->is($role) : $expected === $role->code
        );
    }

    /**
     * Get the permission codes granted by the member's role and team groups.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        $role = $this->role();

        if ($role instanceof Owner) {
            return ['*'];
        }

        if ($role === null) {
            return [];
        }

        $groups = $this->user->groups()->whereBelongsTo($this->team, 'team')->with('permissions')->get();

        return $role->permissions->pluck('code')
            ->merge($groups->flatMap->permissions->pluck('code'))
            ->unique()->values()->all();
    }

    /**
     * Determine if the member has all of the given permissions, on the team or on one of its records.
     */
    public function can(string|array $permissions, ?Model $record = null): bool
    {
        return $this->check((array) $permissions, $record, all: true);
    }

    /**
     * Determine if the member has any of the given permissions, on the team or on one of its records.
     */
    public function canAny(array $permissions, ?Model $record = null): bool
    {
        return $this->check($permissions, $record, all: false);
    }

    /**
     * Change the member's role.
     */
    public function assign(string|Model $role): static
    {
        $this->team->getConnection()->transaction(function () use ($role) {

            $this->team->newQuery()->lockForUpdate()->findOrFail($this->team->getKey());

            if ($this->user->owns($this->team)) {
                throw new RuntimeException('The team owner cannot be changed or removed.');
            }

            if (! $this->user->in($this->team)) {
                throw new RuntimeException('User does not belong to the team.');
            }

            $role = $role instanceof Model ? $role : $this->team->role($role);

            $this->team->members()->updateExistingPivot($this->user->getKey(), ['role_id' => $role->getKey()]);

            $this->team->unsetRelation('members');
            $this->user->unsetRelation('memberships');

            TeamMemberUpdated::dispatch($this->team, $this->user);
        });

        return $this;
    }

    /**
     * Start a rule that allows the member the permission on one record.
     */
    public function allow(string $permission): PendingRule
    {
        return new PendingRule($this->team, $this->user, $permission, forbidden: false);
    }

    /**
     * Start a rule that forbids the member the permission on one record.
     */
    public function forbid(string $permission): PendingRule
    {
        return new PendingRule($this->team, $this->user, $permission, forbidden: true);
    }

    /**
     * Start removing the member's own rule for the permission on one record.
     */
    public function forget(string $permission): PendingRule
    {
        return new PendingRule($this->team, $this->user, $permission, forbidden: null);
    }

    /**
     * Check all, or any, of the permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function check(array $permissions, ?Model $record, bool $all): bool
    {
        if ($permissions === [] || ($record !== null && ! $this->contains($record))) {
            return false;
        }

        if ($this->user->owns($this->team)) {
            return true;
        }

        if ($record === null) {
            $granted = $this->permissions();
            $passes = fn ($permission) => $this->matches($granted, $permission);
        } else {
            $passes = fn ($permission) => $this->allows($permission, $record);
        }

        return $all ? collect($permissions)->every($passes) : collect($permissions)->contains($passes);
    }

    /**
     * Determine if the member may use the permission on the record.
     */
    protected function allows(string $permission, Model $record): bool
    {
        if (method_exists($record, 'isOwner') && $record->isOwner($this->user)) {
            return true;
        }

        [$globalGroups, $teamGroups] = $this->loaded['groups'] ?? $this->groups();

        if ($this->matches($globalGroups->flatMap->permissions->pluck('code')->all(), $permission)) {
            return true;
        }

        $role = $this->loaded === null ? $this->role() : $this->loaded['role'];

        if ($role === null) {
            return false;
        }

        $rules = $this->rules($record, $permission, $teamGroups, $role);

        // The member's own rules decide first, then the rules of their team groups, then of their role.
        return $this->decide($rules('user'), [], $permission)
            ?? $this->decide($rules('groups'), $teamGroups->flatMap->permissions->pluck('code')->all(), $permission)
            ?? $this->decide($rules('role'), $role->permissions->pluck('code')->all(), $permission)
            ?? false;
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
        $codes = $this->codes($permission);

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
    protected function decide(Collection $rules, array $granted, string $permission): ?bool
    {
        if ($rules->contains(fn ($rule) => $rule->pivot->forbidden)) {
            return false;
        }

        return $rules->isNotEmpty() || $this->matches($granted, $permission) ? true : null;
    }

    /**
     * Determine if the record belongs to the team. A record without the team column belongs to any team.
     */
    protected function contains(Model $record): bool
    {
        $teamKey = Config::get('teams.foreign_keys.team_id');

        return ! array_key_exists($teamKey, $record->getAttributes())
            || (string) $record->getAttribute($teamKey) === (string) $this->team->getKey();
    }

    /**
     * Determine if any of the granted codes covers the permission.
     *
     * @param  list<string>  $granted
     */
    protected function matches(array $granted, string $permission): bool
    {
        return array_intersect($this->codes($permission), $granted) !== [];
    }

    /**
     * Get the codes that cover the permission: itself, its parent wildcards and the configured wildcard nodes.
     *
     * @return list<string>
     */
    protected function codes(string $permission): array
    {
        $codes = [$permission];

        while (($position = strrpos($permission, '.')) !== false) {
            $permission = substr($permission, 0, $position);
            $codes[] = "{$permission}.*";
        }

        return Config::get('teams.wildcards.enabled', false)
            ? array_merge(Config::get('teams.wildcards.nodes', []), $codes)
            : $codes;
    }
}
