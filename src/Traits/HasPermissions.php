<?php

namespace Jurager\Teams\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Jurager\Teams\Support\Facades\Teams;
use Jurager\Teams\Support\PendingRule;
use LogicException;

/**
 * Permissions and record rules of a role or a group.
 */
trait HasPermissions
{
    /**
     * Name new records after their code and clean up their assignments on delete.
     */
    public static function bootHasPermissions(): void
    {
        static::creating(function (Model $model) {
            $model->name ??= Str::headline($model->code);
        });

        static::deleting(function (Model $model) {
            $model->permissions()->detach();
            $model->abilities()->detach();
        });
    }

    /**
     * Get the permissions granted to the record.
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(Teams::model('permission'), 'entity', Config::get('teams.tables.entity_permission'), 'entity_id', 'permission_id');
    }

    /**
     * Get the record rules assigned to the record.
     */
    public function abilities(): MorphToMany
    {
        return $this->morphToMany(Teams::model('ability'), 'entity', Config::get('teams.tables.entity_ability'), 'entity_id', 'ability_id')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Grant the given permissions, as codes or code => name pairs.
     */
    public function grant(string|array ...$permissions): static
    {
        $this->permissions()->syncWithoutDetaching($this->permissionIds($permissions));

        return $this->unsetRelation('permissions');
    }

    /**
     * Revoke the given permissions.
     */
    public function revoke(string|array ...$codes): static
    {
        $permissions = $this->permissions()->getRelated();

        $ids = $this->permissions()
            ->whereIn($permissions->qualifyColumn('code'), Arr::flatten($codes))
            ->pluck($permissions->getQualifiedKeyName());

        $this->permissions()->detach($ids);

        return $this->unsetRelation('permissions');
    }

    /**
     * Replace all permissions with the given codes or code => name pairs. Without arguments, removes them all.
     */
    public function sync(string|array ...$permissions): static
    {
        $this->permissions()->sync($this->permissionIds($permissions));

        return $this->unsetRelation('permissions');
    }

    /**
     * Start a rule that allows the permission on one record.
     */
    public function allow(string $permission): PendingRule
    {
        return new PendingRule($this->ruleTeam(), $this, $permission, forbidden: false);
    }

    /**
     * Start a rule that forbids the permission on one record.
     */
    public function forbid(string $permission): PendingRule
    {
        return new PendingRule($this->ruleTeam(), $this, $permission, forbidden: true);
    }

    /**
     * Start removing the rule for the permission on one record.
     */
    public function forget(string $permission): PendingRule
    {
        return new PendingRule($this->ruleTeam(), $this, $permission, forbidden: null);
    }

    /**
     * Resolve permissions to IDs in the record's team, creating missing ones.
     *
     * @param  array<string|array<int|string, string>>  $permissions
     * @return list<int|string>
     */
    protected function permissionIds(array $permissions): array
    {
        $team = $this->getAttribute(Config::get('teams.foreign_keys.team_id'));

        return Teams::model('permission')::ids($team, array_merge(...array_map(Arr::wrap(...), $permissions)));
    }

    /**
     * Get the team the record rules belong to. Global groups have none.
     */
    protected function ruleTeam(): Model
    {
        return $this->team ?? throw new LogicException('Record rules need a team; global groups cannot have them.');
    }
}
