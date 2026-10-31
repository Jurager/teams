<?php

namespace Jurager\Teams;

use BackedEnum;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Jurager\Teams\Facades\Teams;

/** The member's permission sources, optional snapshot, and authorization decisions. */
class MemberPermissions
{
    /** @var array{role: Model|null, groups: array{0: Collection, 1: Collection}, records: array<string, int>, rules: Collection}|null */
    protected ?array $loaded = null;

    public function __construct(protected Member $member) {}

    /**
     * Load the member's role, groups, and rules on the given records at once, so checks on these records run no queries.
     *
     * The loaded data is not refreshed, so load it right before the checks.
     */
    public function load(iterable $records): static
    {
        if ($this->member->user->owns($this->member->team)) {
            return $this;
        }

        $records = collect($records)
            ->filter(fn ($record): bool => $record instanceof Model && Record::from($record)->belongsTo($this->member->team));

        [$global, $groups] = $this->groups();

        $role = $this->member->role()?->load('permissions');

        $rules = collect();

        if ($records->isNotEmpty() && $role !== null) {
            $rules = $this->member->team->abilities()
                ->forRecords($records)
                ->forRecipients([$this->member->user, ...$groups, $role])
                ->with('permission')->get();
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
     * Get the permission codes granted by the member's role and team groups.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        if ($this->member->user->owns($this->member->team)) {
            return ['*'];
        }

        return $this->permissionSources()->flatten()->unique()->values()->all();
    }

    /**
     * Check all, or any, of the permissions.
     *
     * @param  list<string|BackedEnum>  $permissions
     */
    public function check(array $permissions, Model|string|null $record, bool $all, int|string|null $key = null): Response
    {
        if ($permissions === []) {
            return Response::deny(__('No permission was requested.'), 'missing_permission');
        }

        if ($record === null && $key !== null) {
            throw new InvalidArgumentException('A record key requires a record type.');
        }

        $record = $record === null ? null : Record::from($record, $key);

        if ($record !== null && ! $record->belongsTo($this->member->team)) {
            return Response::deny(__('The record belongs to another team.'), 'team_mismatch');
        }

        if ($this->member->user->owns($this->member->team)) {
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
    protected function allows(string $permission, Record $record): Response
    {
        if ($record->model !== null && method_exists($record->model, 'isOwner') && $record->model->isOwner($this->member->user)) {
            return Response::allow(__('The user owns the record.'), 'record_owner');
        }

        [$globalGroups, $teamGroups] = $this->loaded['groups'] ?? $this->groups();

        if ($this->matches($globalGroups->flatMap->permissions->pluck('code')->all(), $permission)) {
            return Response::allow(__('Granted by a global group.'), 'global_group');
        }

        $role = $this->loaded === null ? $this->member->role() : $this->loaded['role'];

        if ($role === null) {
            return Response::deny(__('The user does not belong to the team.'), 'not_member');
        }

        $rules = $this->rules($record, $permission, $teamGroups, $role);

        // The member's own rules decide first, then the rules of their team groups, then of their role.
        return $this->decide($rules->get($this->key($this->member->user), collect()), [], $permission, 'user')
            ?? $this->decide($teamGroups->flatMap(fn (Model $group) => $rules->get($this->key($group), collect())), $teamGroups->flatMap->permissions->pluck('code')->all(), $permission, 'group')
            ?? $this->decide($rules->get($this->key($role), collect()), $role->permissions->pluck('code')->all(), $permission, 'role')
            ?? Response::deny(__('No permission grants access.'), 'missing_permission');
    }

    /**
     * Get the team-wide permission codes by their source. Non-members have no sources.
     *
     * @return Collection<string, list<string>>
     */
    protected function permissionSources(): Collection
    {
        $role = $this->member->role();

        if ($role === null) {
            return collect();
        }

        $groups = $this->member->user->groups()->whereBelongsTo($this->member->team, 'team')->with('permissions')->get();

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

        [$global, $team] = $this->member->user->groups()
            ->where(fn ($query) => $query->whereBelongsTo($this->member->team, 'team')->orWhereNull($teamKey))
            ->with('permissions')
            ->get()
            ->partition(fn ($group) => $group->getAttribute($teamKey) === null);

        return [$global, $team];
    }

    /** Get matching rules grouped by recipient, from the snapshot or a single query. */
    protected function rules(Record $record, string $permission, Collection $groups, Model $role): Collection
    {
        $key = $record->key();
        $codes = Teams::model('permission')::codes($permission);

        $rules = isset($this->loaded['records'][$key])
            ? $this->loaded['rules']->filter(fn (Model $rule): bool => "{$rule->entity_type}:{$rule->entity_id}" === $key
                && in_array($rule->permission?->code, $codes, true))
            : $this->member->team->abilities()
                ->forRecord($record)
                ->forRecipients([$this->member->user, ...$groups, $role])
                ->whereHas('permission', fn ($query) => $query->whereIn('code', $codes))
                ->get();

        return $rules->groupBy(fn (Model $rule): string => "{$rule->recipient_type}:{$rule->recipient_id}");
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
        $rules = $rules->filter(fn (Model $rule): bool => $rule->expires_at?->isFuture() ?? true);

        if ($rules->contains(fn ($rule) => $rule->forbidden)) {
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
     * Determine if any of the granted codes covers the permission.
     *
     * @param  list<string>  $granted
     */
    protected function matches(array $granted, string $permission): bool
    {
        return array_intersect(Teams::model('permission')::codes($permission), $granted) !== [];
    }
}
