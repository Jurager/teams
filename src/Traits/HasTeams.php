<?php

namespace Jurager\Teams\Traits;

use Exception;
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
    private const int LEVEL_DEFAULT         = 0;
    private const int LEVEL_FORBIDDEN       = 1;
    private const int LEVEL_ROLE_ALLOWED    = 2;
    private const int LEVEL_ROLE_FORBIDDEN  = 3;
    private const int LEVEL_GROUP_ALLOWED   = 4;
    private const int LEVEL_GROUP_FORBIDDEN = 5;
    private const int LEVEL_USER_ALLOWED    = 5;
    private const int LEVEL_USER_FORBIDDEN  = 6;
    private const int LEVEL_GLOBAL_ALLOWED  = 6;

    /**
     * @var array Used to hold decisions made
     */
    private array $decisionCache = [];

    /**
     * Check if the user owns the given team.
     */
    public function ownsTeam(object $team): bool
    {
        return $this->id === $team->{$this->getForeignKey()};
    }

    /**
     * Retrieve all teams the user owns or belongs to.
     */
    public function allTeams(): Collection
    {
        return $this->ownedTeams->merge($this->teams)->sortBy('name');
    }

    /**
     * Retrieve all teams the user owns.
     *
     * @throws Exception
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(TeamsFacade::model('team'))->withoutGlobalScopes();
    }

    /**
     * Retrieve all teams the user belongs to.
     *
     * @throws Exception
     */
    public function teams(): BelongsToMany
    {
        return $this
            ->belongsToMany(TeamsFacade::model('team'), TeamsFacade::model('membership'), 'user_id', Config::get('teams.foreign_keys.team_id'))
            ->withoutGlobalScopes()
            ->withPivot('role_id')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Retrieve abilities related to the user.
     *
     * @throws Exception
     */
    public function abilities(): MorphToMany
    {
        return $this->morphToMany(TeamsFacade::model('ability'), 'entity', 'entity_ability')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Retrieve all groups the user belongs to.
     *
     * @throws Exception
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(TeamsFacade::model('group'), 'group_user', 'user_id', 'group_id');
    }

    /**
     * Check if the user belongs to the specified team.
     *
     * @throws Exception
     */
    public function belongsToTeam(object $team): bool
    {
        return $this->ownsTeam($team) || $this->teams()->where(Config::get('teams.foreign_keys.team_id', 'team_id'), $team->id)->exists();
    }

    /**
     * Retrieve the user's role in a team.
     *
     * @throws Exception
     */
    public function teamRole(object $team): mixed
    {
        if ($this->ownsTeam($team)) {
            return new Owner();
        }

        return $this->belongsToTeam($team)
            ? $team->getRole($this->teams()->find($team->id)?->membership?->role_id)
            : null;
    }

    /**
     * Check if the user has the specified role on the team.
     *
     * @throws Exception
     */
    public function hasTeamRole(object $team, string|array $roles, bool $require = false): bool
    {
        if ($this->ownsTeam($team)) {
            return true;
        }

        $userRole = $this->teamRole($team)?->code;

        $roles = (array) $roles;

        return $require
            ? ! array_diff($roles, [$userRole])
            : in_array($userRole, $roles, true);
    }

    /**
     * Get the user's permissions for the given team.
     *
     * @param  string|null  $scope  Scope of permissions to get (ex. 'role', 'group'), by default getting all permissions
     * @return array|string[]
     *
     * @throws Exception
     */
    public function teamPermissions(object $team, ?string $scope = null): array
    {
        if ($this->ownsTeam($team)) {
            return ['*'];
        }

        $permissions = [];

        if (! $scope || $scope === 'role') {
            $permissions = array_merge($permissions, $this->teamRole($team)?->permissions?->pluck('code')?->toArray() ?? []);
        }

        if (! $scope || $scope === 'group') {
            $groupPermissions = $this->groups()->where(Config::get('teams.foreign_keys.team_id', 'team_id'), $team->id)
                ->with('permissions')
                ->get()
                ->flatMap(fn ($group) => $group->permissions->pluck('code'))
                ->toArray();
            $permissions = array_merge($permissions, $groupPermissions);
        }

        return array_unique($permissions);
    }

    /**
     * Determine if the user has the given permission on the given team.
     *
     * $require = true  — all permissions in the array are required
     * $require = false — only one or more permissions are required (or $permissions is empty)
     *
     * @param  string|null  $scope  Scope of permissions to check (ex. 'role', 'group'), by default checking all permissions
     *
     * @throws Exception
     */
    public function hasTeamPermission(object $team, string|array $permissions, bool $require = false, ?string $scope = null): bool
    {
        if (! Config::get('teams.request.cache_decisions', false)) {
            return $this->determineTeamPermission($team, $permissions, $require, $scope);
        }

        $cacheKey = hash('sha256', serialize([
            $this->getKey(),
            $team->getKey(),
            $permissions,
            $require,
            $scope,
        ]));

        if (! isset($this->decisionCache[$cacheKey])) {
            $this->decisionCache[$cacheKey] = $this->determineTeamPermission($team, $permissions, $require, $scope);
        }

        return $this->decisionCache[$cacheKey];
    }

    /**
     * Determine if the user has the given permission on the given team.
     *
     * $require = true  — all permissions in the array are required
     * $require = false — only one or more permissions are required (or $permissions is empty)
     *
     * @throws Exception
     */
    protected function determineTeamPermission(object $team, string|array $permissions, bool $require = false, ?string $scope = null): bool
    {
        if ($this->ownsTeam($team)) {
            return true;
        }

        $permissions = (array) $permissions;

        if (empty($permissions)) {
            return false;
        }

        $userPermissions = $this->teamPermissions($team, $scope);

        foreach ($permissions as $permission) {
            $hasPermission = $this->checkPermissionWildcard($userPermissions, $permission);

            if ($hasPermission && ! $require) {
                return true;
            }

            if (! $hasPermission && $require) {
                return false;
            }
        }

        return $require;
    }

    /**
     * Get all abilities for the user on a specific entity within a team.
     *
     * @throws Exception
     */
    public function teamAbilities(object $team, object $entity, bool $forbidden = false): mixed
    {
        $query = $this->abilities()->where([
            Config::get('teams.foreign_keys.team_id', 'team_id') => $team->id,
            'abilities.entity_id' => $entity->getKey(),
            'abilities.entity_type' => $entity->getMorphClass(),
        ]);

        if ($forbidden) {
            $query->wherePivot('forbidden', true);
        }

        return $query->get();
    }

    /**
     * Determine if the user has global group permissions for a given ability.
     *
     * Used for universal groups that are not tied to a specific team —
     * e.g. a global moderator group that spans all teams.
     */
    private function hasGlobalGroupPermissions(string $ability): bool
    {
        $permissions = $this->groups->whereNull(Config::get('teams.foreign_keys.team_id', 'team_id'))
            ->load('permissions')
            ->flatMap(fn ($group) => $group->permissions->pluck('code'))
            ->toArray();

        return $this->checkPermissionWildcard($permissions, $ability);
    }

    /**
     * Determine if the user can perform an action on a specific entity within a team.
     *
     * @param  object|string  $action_entity  Eloquent model or fully-qualified class name
     * @param  int|string|null  $action_entity_id  Required when $action_entity is a class name string
     *
     * @throws Exception
     */
    public function hasTeamAbility(object $team, string $permission, object|string $action_entity, int|string|null $action_entity_id = null): bool
    {
        if ($this->ownsTeam($team) || (is_object($action_entity) && method_exists($action_entity, 'isOwner') && $action_entity->isOwner($this))) {
            return true;
        }

        [$entityType, $entityId] = $this->resolveEntity($action_entity, $action_entity_id);

        $allowed  = self::LEVEL_DEFAULT;
        $forbidden = self::LEVEL_FORBIDDEN;

        if ($this->hasTeamPermission($team, $permission, scope: 'role')) {
            $allowed = max($allowed, self::LEVEL_ROLE_ALLOWED);
        }

        if ($this->hasTeamPermission($team, $permission, scope: 'group')) {
            $allowed = max($allowed, self::LEVEL_GROUP_ALLOWED);
        }

        if ($this->hasGlobalGroupPermissions($permission)) {
            $allowed = max($allowed, self::LEVEL_GLOBAL_ALLOWED);
        }

        $permissionIds = $this->resolvePermissionIds($team, $permission);

        $loadAbilities = fn ($query) => $query->where([
            'abilities.entity_id' => $entityId,
            'abilities.entity_type' => $entityType,
        ])->whereIn('permission_id', $permissionIds);

        $role = $this->teamRole($team);

        if ($role === null) {
            return false;
        }

        $role->load(['abilities' => $loadAbilities]);
        $groups = $this->groups->where(Config::get('teams.foreign_keys.team_id', 'team_id'), $team->id)->load(['abilities' => $loadAbilities]);
        $this->load(['abilities' => $loadAbilities]);

        foreach ([$role, ...$groups, $this] as $entity) {
            foreach ($entity->abilities as $ability) {
                [$allowedLevel, $forbiddenLevel] = $this->abilityLevels($entity);

                if ($ability->pivot->forbidden) {
                    $forbidden = max($forbidden, $forbiddenLevel);
                } else {
                    $allowed = max($allowed, $allowedLevel);
                }
            }
        }

        return $allowed >= $forbidden;
    }

    /**
     * Allow the user to perform an ability on an entity.
     *
     * @param  object|string  $action_entity  Eloquent model or fully-qualified class name
     * @param  int|string|null  $action_entity_id  Required when $action_entity is a class name string
     * @param  object|null  $target_entity  Defaults to the user if null
     *
     * @throws Exception
     */
    public function allowTeamAbility(object $team, string $permission, object|string $action_entity, int|string|null $action_entity_id = null, ?object $target_entity = null): void
    {
        $this->updateAbilityOnEntity($team, 'syncWithoutDetaching', $permission, $action_entity, $action_entity_id, $target_entity);
    }

    /**
     * Forbid the user from performing an ability on an entity.
     *
     * @param  object|string  $action_entity  Eloquent model or fully-qualified class name
     * @param  int|string|null  $action_entity_id  Required when $action_entity is a class name string
     * @param  object|null  $target_entity  Defaults to the user if null
     *
     * @throws Exception
     */
    public function forbidTeamAbility(object $team, string $permission, object|string $action_entity, int|string|null $action_entity_id = null, ?object $target_entity = null): void
    {
        $this->updateAbilityOnEntity($team, 'syncWithoutDetaching', $permission, $action_entity, $action_entity_id, $target_entity, true);
    }

    /**
     * Remove an ability rule for the user on an entity.
     *
     * @param  object|string  $action_entity  Eloquent model or fully-qualified class name
     * @param  int|string|null  $action_entity_id  Required when $action_entity is a class name string
     * @param  object|null  $target_entity  Defaults to the user if null
     *
     * @throws Exception
     */
    public function deleteTeamAbility(object $team, string $permission, object|string $action_entity, int|string|null $action_entity_id = null, ?object $target_entity = null): void
    {
        $this->updateAbilityOnEntity($team, 'detach', $permission, $action_entity, $action_entity_id, $target_entity);
    }

    /**
     * Attach or detach an ability rule on an entity.
     *
     * @param  string  $method  Eloquent relation method: 'syncWithoutDetaching' or 'detach'
     *
     * @throws Exception
     */
    private function updateAbilityOnEntity(object $team, string $method, string $permission, object|string $action_entity, int|string|null $action_entity_id = null, ?object $target_entity = null, bool $forbidden = false): void
    {
        [$entityType, $entityId] = $this->resolveEntity($action_entity, $action_entity_id);

        $abilityModel = TeamsFacade::instance('ability')->firstOrCreate([
            Config::get('teams.foreign_keys.team_id', 'team_id') => $team->id,
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'permission_id' => $team->getPermissionIds([$permission])[0],
        ]);

        $targetEntity = $target_entity ?? $this;
        $relation = $this->getRelationName($targetEntity);

        $abilityModel->{$relation}()->{$method}([$targetEntity->getKey() => [
            'forbidden' => $forbidden,
        ]]);
    }

    /**
     * Resolve entity type and ID from an Eloquent model or a class name string + ID.
     *
     * @param  object|string  $entity  Eloquent model or fully-qualified class name
     * @param  int|string|null  $id  Required when $entity is a string
     * @return array{string, int|string}
     */
    private function resolveEntity(object|string $entity, int|string|null $id = null): array
    {
        if (is_object($entity)) {
            return [$entity->getMorphClass(), $entity->getKey()];
        }

        if ($id === null) {
            throw new InvalidArgumentException('Entity ID is required when passing a class name string to ability methods.');
        }

        return [$entity, $id];
    }

    /**
     * Resolve permission IDs for the given permission string within a team.
     * Expands dot-notation into wildcard codes (e.g. 'a.b.c' → ['a.*', 'a.b.*', 'a.b.c']).
     */
    private function resolvePermissionIds(object $team, string $permission): array
    {
        return TeamsFacade::model('permission')::query()
            ->where(Config::get('teams.foreign_keys.team_id', 'team_id'), $team->id)
            ->whereIn('code', $this->permissionCodes($permission))
            ->pluck('id')
            ->all();
    }

    /**
     * Resolve the Ability relation name for the given target entity.
     */
    private function getRelationName(object $entity): string
    {
        $groupClass = TeamsFacade::model('group');
        $roleClass = TeamsFacade::model('role');

        return match (true) {
            $entity instanceof $groupClass => 'groups',
            $entity instanceof $roleClass => 'roles',
            default => 'users',
        };
    }

    /**
     * Return [allowedLevel, forbiddenLevel] for an entity in the ability evaluation loop.
     *
     * @return array{int, int}
     */
    private function abilityLevels(object $entity): array
    {
        $roleClass = TeamsFacade::model('role');
        $groupClass = TeamsFacade::model('group');

        return match (true) {
            $entity instanceof $roleClass  => [self::LEVEL_ROLE_ALLOWED,  self::LEVEL_ROLE_FORBIDDEN],
            $entity instanceof $groupClass => [self::LEVEL_GROUP_ALLOWED, self::LEVEL_GROUP_FORBIDDEN],
            default                        => [self::LEVEL_USER_ALLOWED,  self::LEVEL_USER_FORBIDDEN],
        };
    }

    /**
     * Build all permission codes for wildcard matching from a dot-notation permission string.
     * e.g. 'articles.edit' → ['articles.*', 'articles.edit']
     */
    private function permissionCodes(string $permission): array
    {
        $segments = collect(explode('.', $permission));

        $codes = $segments->map(
            fn ($_, $key) => $segments->take($key + 1)->implode('.').($key + 1 === $segments->count() ? '' : '.*')
        );

        if (Config::get('teams.wildcards.enabled', false)) {
            $codes = collect(Config::get('teams.wildcards.nodes', []))->merge($codes);
        }

        return $codes->all();
    }

    /**
     * Check if a permission matches any of the user's permissions, including wildcards.
     */
    private function checkPermissionWildcard(array $userPermissions, string $permission): bool
    {
        return ! empty(array_intersect($this->permissionCodes($permission), $userPermissions));
    }
}
