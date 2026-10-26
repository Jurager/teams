<?php

namespace Jurager\Teams\Traits;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Jurager\Teams\Models\Owner;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

trait HasTeams
{
    /**
     * Determine if the user owns the given team.
     */
    public function ownsTeam(Model $team): bool
    {
        return $this->getKey() !== null && $team->user_id !== null
            && (string) $this->getKey() === (string) $team->user_id;
    }

    /**
     * Get all the teams the user owns or belongs to.
     */
    public function allTeams(): Collection
    {
        return $this->ownedTeams->merge($this->teams)->sortBy('name');
    }

    /**
     * Get all the teams the user owns.
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(TeamsFacade::model('team'), 'user_id');
    }

    /**
     * Get all the teams the user belongs to.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(TeamsFacade::model('team'), TeamsFacade::model('membership'), 'user_id', Config::get('teams.foreign_keys.team_id'))
            ->withPivot('role_id')->withTimestamps()->as('membership');
    }

    /**
     * Get all the abilities assigned to the user.
     */
    public function abilities(): MorphToMany
    {
        return $this->morphToMany(TeamsFacade::model('ability'), 'entity', Config::get('teams.tables.entity_ability'), 'entity_id', 'ability_id')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Get all the groups the user belongs to.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(TeamsFacade::model('group'), Config::get('teams.tables.group_user'), 'user_id', 'group_id');
    }

    /**
     * Determine if the user owns or belongs to the given team.
     */
    public function belongsToTeam(Model $team): bool
    {
        return $this->ownsTeam($team) || $this->teams()->whereKey($team->getKey())->exists();
    }

    /**
     * Get the role of the user on the given team.
     */
    public function teamRole(Model $team): Model|Owner|null
    {
        if ($this->ownsTeam($team)) {
            return new Owner;
        }

        $roles = $team->roles();
        $memberships = $this->teams();

        return $roles->whereIn($roles->getRelated()->getQualifiedKeyName(), $memberships->whereKey($team->getKey())
            ->select($memberships->qualifyPivotColumn('role_id')))->first();
    }

    /**
     * Determine if the user has any, or all, of the given roles on the team.
     */
    public function hasTeamRole(Model $team, string|array $roles, bool $require = false): bool
    {
        if ($this->ownsTeam($team)) {
            return true;
        }

        $userRole = $this->teamRole($team)?->code;
        $roles = (array) $roles;

        if ($userRole === null || $roles === []) {
            return false;
        }

        return $require ? ! array_diff($roles, [$userRole]) : in_array($userRole, $roles, true);
    }

    /**
     * Get the permission codes granted to the user on the team by their role and groups.
     *
     * @return list<string>
     */
    public function teamPermissions(Model $team, ?string $scope = null): array
    {
        if ($this->ownsTeam($team)) {
            return ['*'];
        }

        if (! in_array($scope, [null, 'role', 'group'], true)) {
            return [];
        }

        $role = $this->teamRole($team);

        if ($role === null) {
            return [];
        }

        $permissions = collect();

        if ($scope !== 'group') {
            $permissions = $role->permissions->pluck('code');
        }

        if ($scope !== 'role') {
            $groups = $this->groups()->whereBelongsTo($team, 'team')->with('permissions')->get();
            $permissions = $permissions->merge($groups->flatMap->permissions->pluck('code'));
        }

        return $permissions->unique()->values()->all();
    }

    /**
     * Determine if the user has any, or all, of the given permissions on the team.
     */
    public function hasTeamPermission(Model $team, string|array $permissions, bool $require = false, ?string $scope = null): bool
    {
        if (! Config::get('teams.request.cache_decisions', false)
            || $this->getConnection()->transactionLevel() > 0
            || $team->getConnection()->transactionLevel() > 0) {
            return $this->determineTeamPermission($team, $permissions, $require, $scope);
        }

        $key = hash('sha256', serialize([
            static::class, $this->getConnectionName(), $this->getKey(),
            $team::class, $team->getConnectionName(), $team->getKey(),
            $permissions, $require, $scope,
        ]));

        return TeamsFacade::rememberPermission($key, fn () => $this->determineTeamPermission($team, $permissions, $require, $scope));
    }

    /**
     * Get the user's own ability rules for the given record on the team.
     */
    public function teamAbilities(Model $team, Model $entity, bool $forbidden = false): Collection
    {
        $abilities = $this->abilities()->whereBelongsTo($team, 'team')->where($this->entityConstraint($entity));

        if ($forbidden) {
            $abilities->wherePivot('forbidden', true);
        }

        return $abilities->get();
    }

    /**
     * Determine if the user can perform the given action on the record.
     */
    public function hasTeamAbility(Model $team, string $permission, Model|string $actionEntity, int|string|null $actionEntityId = null): bool
    {
        if ($this->ownsTeam($team)) {
            return true;
        }

        [$entity] = $this->resolveEntity($actionEntity, $actionEntityId);

        if ($this->ownsEntity($entity)) {
            return true;
        }

        $teamKey = Config::get('teams.foreign_keys.team_id');

        [$globalGroups, $teamGroups] = $this->groups()
            ->where(fn ($query) => $query->whereBelongsTo($team, 'team')->orWhereNull($teamKey))
            ->with('permissions')
            ->get()
            ->partition(fn ($group) => $group->getAttribute($teamKey) === null);

        if ($this->matchesPermission($globalGroups->flatMap->permissions->pluck('code')->all(), $permission)) {
            return true;
        }

        $role = $this->teamRole($team);

        if ($role === null) {
            return false;
        }

        $rules = $this->matchingAbilityRules($team, $entity, $permission);

        // The user's own rules come first, then the rules of their team groups, then of their role.
        return $this->abilityDecision($rules($this->abilities())->get(), new Collection, $permission)
            ?? $this->abilityDecision($teamGroups->load(['abilities' => $rules])->flatMap->abilities, $teamGroups->flatMap->permissions, $permission)
            ?? $this->abilityDecision($role->load(['permissions', 'abilities' => $rules])->abilities, $role->permissions, $permission)
            ?? false;
    }

    /**
     * Allow the action on the record for the user or the given target.
     */
    public function allowTeamAbility(Model $team, string $permission, Model|string $actionEntity, int|string|null $actionEntityId = null, ?Model $targetEntity = null): void
    {
        $this->updateAbilityOnEntity($team, $permission, $actionEntity, $actionEntityId, $targetEntity, false);
    }

    /**
     * Forbid the action on the record for the user or the given target.
     */
    public function forbidTeamAbility(Model $team, string $permission, Model|string $actionEntity, int|string|null $actionEntityId = null, ?Model $targetEntity = null): void
    {
        $this->updateAbilityOnEntity($team, $permission, $actionEntity, $actionEntityId, $targetEntity, true);
    }

    /**
     * Remove the rule for the action on the record from the user or the given target.
     */
    public function deleteTeamAbility(Model $team, string $permission, Model|string $actionEntity, int|string|null $actionEntityId = null, ?Model $targetEntity = null): void
    {
        [$entity] = $this->resolveEntity($actionEntity, $actionEntityId);

        $permissions = $team->permissions();
        $permissionId = $permissions->where('code', $permission)->value($permissions->getRelated()->getKeyName());

        if ($permissionId === null) {
            return;
        }

        $abilities = $team->abilities();
        $abilityId = $abilities->where($this->entityConstraint($entity))->where('permission_id', $permissionId)
            ->value($abilities->getRelated()->getKeyName());

        if ($abilityId !== null) {
            ($targetEntity ?? $this)->abilities()->detach($abilityId);
        }
    }

    /**
     * Resolve the permission check without the decision cache.
     */
    protected function determineTeamPermission(Model $team, string|array $permissions, bool $require = false, ?string $scope = null): bool
    {
        if ($this->ownsTeam($team)) {
            return true;
        }

        $permissions = collect((array) $permissions);

        if ($permissions->isEmpty()) {
            return false;
        }

        $granted = $this->teamPermissions($team, $scope);
        $matches = fn ($permission) => $this->matchesPermission($granted, $permission);

        return $require ? $permissions->every($matches) : $permissions->contains($matches);
    }

    /**
     * Determine if the user owns the record through its own isOwner() method.
     */
    protected function ownsEntity(Model $entity): bool
    {
        if (! method_exists($entity, 'isOwner')) {
            return false;
        }

        $record = $entity->exists ? $entity : $entity->newQuery()->find($entity->getKey());

        return (bool) $record?->isOwner($this);
    }

    /**
     * Build a constraint for the team's ability rules on the record that grant the permission.
     */
    protected function matchingAbilityRules(Model $team, Model $entity, string $permission): Closure
    {
        $permissions = $team->permissions();

        $permissionIds = $permissions
            ->whereIn($permissions->getRelated()->qualifyColumn('code'), $this->permissionCodes($permission))
            ->select($permissions->getRelated()->getQualifiedKeyName());

        return fn ($query) => $query->whereBelongsTo($team, 'team')
            ->where($this->entityConstraint($entity))
            ->whereIn($query->qualifyColumn('permission_id'), $permissionIds);
    }

    /**
     * Decide from one level of rules: a forbid denies, an allow or a matching permission grants.
     *
     * Returns null when this level has nothing to say, so the next level decides.
     */
    protected function abilityDecision(Collection $rules, Collection $permissions, string $permission): ?bool
    {
        if ($rules->contains(fn ($rule) => $rule->pivot->forbidden)) {
            return false;
        }

        if ($rules->isNotEmpty() || $this->matchesPermission($permissions->pluck('code')->all(), $permission)) {
            return true;
        }

        return null;
    }

    /**
     * Create the ability rule if needed and assign it to the target.
     */
    protected function updateAbilityOnEntity(Model $team, string $permission, Model|string $actionEntity, int|string|null $actionEntityId, ?Model $targetEntity, bool $forbidden): void
    {
        [$entity, $entityId] = $this->resolveEntity($actionEntity, $actionEntityId);

        $team->getConnection()->transaction(function () use ($team, $permission, $entity, $entityId, $targetEntity, $forbidden) {
            $ability = $team->abilities()->firstOrCreate([
                'entity_id' => $entityId,
                'entity_type' => $entity->getMorphClass(),
                'permission_id' => $team->getPermissionIds([$permission])[0],
            ]);

            $ability->lockForUpdate()->findOrFail($ability->getKey());

            ($targetEntity ?? $this)->abilities()->syncWithoutDetaching([
                $ability->getKey() => ['forbidden' => $forbidden],
            ]);
        });
    }

    /**
     * Resolve a record instance, or a model class and id, to a model carrying the id.
     *
     * @return array{Model, int|string}
     */
    protected function resolveEntity(Model|string $entity, int|string|null $id = null): array
    {
        if (is_string($entity) && ! is_subclass_of($entity, Model::class)) {
            throw new InvalidArgumentException('The entity must be an Eloquent model or model class.');
        }

        $id = $entity instanceof Model ? $entity->getKey() : $id;

        if ($id === null) {
            throw new InvalidArgumentException('A persisted entity or an entity class and ID is required.');
        }

        if (is_string($entity)) {
            $entity = new $entity;
            $entity->setAttribute($entity->getKeyName(), $id);
        }

        return [$entity, $id];
    }

    /**
     * Constrain an ability query to the given record.
     */
    protected function entityConstraint(Model $entity): Closure
    {
        return fn (Builder $query) => $query->where([
            $query->qualifyColumn('entity_type') => $entity->getMorphClass(),
            $query->qualifyColumn('entity_id') => $entity->getKey(),
        ]);
    }

    /**
     * Determine if any of the granted codes covers the permission.
     *
     * @param  list<string>  $granted
     */
    protected function matchesPermission(array $granted, string $permission): bool
    {
        return array_intersect($this->permissionCodes($permission), $granted) !== [];
    }

    /**
     * Get the codes that grant the permission: itself, its parent wildcards and the configured wildcard nodes.
     *
     * @return list<string>
     */
    protected function permissionCodes(string $permission): array
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
