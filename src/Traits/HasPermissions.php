<?php

namespace Jurager\Teams\Traits;

use BackedEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Jurager\Teams\Events\PermissionsUpdated;
use Jurager\Teams\Support\Facades\Teams;
use Jurager\Teams\Support\PendingRule;
use LogicException;

/**
 * Permissions and record rules of a role or a group.
 */
trait HasPermissions
{
    /**
     * Store enum codes as strings.
     */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string|BackedEnum $code) => $code instanceof BackedEnum ? $code->value : $code);
    }

    /**
     * Name new records after their code and clean up their assignments on delete.
     */
    public static function bootHasPermissions(): void
    {
        static::creating(function (Model $model) {
            $model->name ??= Str::headline($model->code);
        });

        static::deleted(function (Model $model) {
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
    public function grant(string|BackedEnum|array ...$permissions): static
    {
        return $this->syncPermissions($permissions, detaching: false);
    }

    /**
     * Revoke the given permissions.
     */
    public function revoke(string|BackedEnum|array ...$codes): static
    {
        $this->getConnection()->transaction(function () use ($codes) {

            $this->lockForUpdate()->findOrFail($this->getKey());

            $permissions = $this->permissions()->getRelated();
            $ids = $this->permissions()
                ->whereIn($permissions->qualifyColumn('code'), array_map(fn ($code) => $code instanceof BackedEnum ? $code->value : $code, Arr::flatten($codes)))
                ->pluck($permissions->getQualifiedKeyName())->all();

            if ($ids !== []) {
                $this->permissions()->detach($ids);
                $this->unsetRelation('permissions');

                PermissionsUpdated::dispatch($this, [], $ids);
            }
        });

        return $this->unsetRelation('permissions');
    }

    /**
     * Replace all permissions with the given codes or code => name pairs. Without arguments, removes them all.
     */
    public function sync(string|BackedEnum|array ...$permissions): static
    {
        return $this->syncPermissions($permissions);
    }

    /**
     * Start a rule that allows the permission on one record.
     */
    public function allow(string|BackedEnum $permission): PendingRule
    {
        return new PendingRule($this->ruleTeam(), $this, $permission, forbidden: false);
    }

    /**
     * Start a rule that forbids the permission on one record.
     */
    public function forbid(string|BackedEnum $permission): PendingRule
    {
        return new PendingRule($this->ruleTeam(), $this, $permission, forbidden: true);
    }

    /**
     * Start removing the rule for the permission on one record.
     */
    public function forget(string|BackedEnum $permission): PendingRule
    {
        return new PendingRule($this->ruleTeam(), $this, $permission, forbidden: null);
    }

    /**
     * Sync permissions and dispatch their actual assignment changes.
     */
    protected function syncPermissions(array $permissions, bool $detaching = true): static
    {
        $this->getConnection()->transaction(function () use ($permissions, $detaching) {

            $this->lockForUpdate()->findOrFail($this->getKey());

            $changes = $this->permissions()->sync($this->permissionIds($permissions), $detaching);
            $this->unsetRelation('permissions');

            if ($changes['attached'] !== [] || $changes['detached'] !== []) {
                PermissionsUpdated::dispatch($this, array_values($changes['attached']), array_values($changes['detached']));
            }
        });

        return $this;
    }

    /**
     * Resolve permissions to IDs in the record's team, creating missing ones.
     *
     * @param  array<string|BackedEnum|array<int|string, string|BackedEnum>>  $permissions
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
