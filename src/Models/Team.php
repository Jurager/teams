<?php

namespace Jurager\Teams\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jurager\Teams\Owner;
use Jurager\Teams\Teams;
use RuntimeException;

class Team extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['user_id', 'name'];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [
        'roles.capabilities',
        'groups',
    ];

    /**
     * Get the owner of the team.
     *
     * @return BelongsTo
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Teams::$userModel, 'user_id');
    }

    /**
     * Get all users of the team.
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$userModel, Teams::$membershipModel)
            ->withPivot('role_id')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Get all the abilities belong to the team.
     *
     * @return BelongsToMany
     */
    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$abilityModel, Teams::$permissionModel)
            ->withTimestamps()
            ->withPivot(['entity_type', 'entity_id'])
            ->as('permission');
    }

    /**
     * Get all roles of the team.
     *
     * @return HasMany
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Teams::$roleModel);
    }

    /**
     * Get all groups of the team.
     *
     * @return HasMany
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Teams::$groupModel);
    }

    /**
     * Get all the pending user invitations for the team.
     *
     * @return HasMany
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Teams::$invitationModel);
    }

    /**
     * Return all users in a team
     * @return Collection
     */
    public function allUsers(): Collection
    {
        return $this->users->merge([$this->owner]);
    }

    /**
     * Checks if team has user
     *
     * @param object $user
     * @return bool
     */
    public function hasUser(object $user): bool
    {
        return $this->users->contains($user) || $user->ownsTeam($this);
    }

    /**
     * Checks if team has user with given email
     * @param string $email
     * @return bool
     */
    public function hasUserWithEmail(string $email): bool
    {
        return $this->allUsers()->contains(fn($user) => $user->email === $email);
    }

    /**
     * Returns user's role in team
     *
     * @param object $user
     * @return object|null
     */
    public function userRole(object $user): object|null
    {
        return $this->owner === $user ? new Owner : $this->findRole($this->users->where('id', $user->id)->first()->membership->role->id ?? null);
    }

    /**
     * @param object $user
     * @param string|array $permission
     * @param bool $require
     * @return bool
     */
    public function userHasPermission(object $user, string|array $permission, bool $require = false): bool
    {
        return $user->hasTeamPermission($this, $permission, $require);
    }

    /**
     * @return bool
     */
    public function hasRoles(): bool
    {
        return $this->roles()->exists();
    }

    /**
     * @param string $name
     * @param array $capabilities
     * @return object
     */
    public function addRole(string $name, array $capabilities): object
    {
        return \DB::transaction(function () use ($name, $capabilities) {

            $role = $this->roles()->firstWhere('name', $name);

            if ($role) {
                throw new RuntimeException("Role with name '{$name}' already exists.");
            }

            $role = $this->roles()->create(['name' => $name]);

            $capabilityIds = $this->getCapabilityIds($capabilities);

            if (!empty($capabilityIds)) {
                $role->capabilities()->sync($capabilityIds);
            }

            return $role;
        });
    }

    /**
     * @param string $name
     * @param array $capabilities
     * @return object|bool
     */
    public function updateRole(string $name, array $capabilities): object|bool
    {
        return DB::transaction(function () use ($name, $capabilities) {

            $role = $this->roles()->firstWhere('name', $name);

            if (!$role) {
                throw new ModelNotFoundException("Role with name '{$name}' not found.");
            }

            $capability_ids = $this->getCapabilityIds($capabilities);

            if (!empty($capability_ids)) {
                $role->capabilities()->sync($capability_ids);
            } else {
                $role->capabilities()->detach();
            }

            return $role;
        });
    }

    /**
     * Deletes the given role from team
     *
     * @param string $name
     * @return bool
     */
    public function deleteRole(string $name): bool
    {
        return DB::transaction(function () use ($name) {

            $role = $this->roles()->firstWhere('name', $name);

            if (!$role) {
                throw new ModelNotFoundException("Role with name '{$name}' not found.");
            }

            return $role->delete();
        });
    }

    /**
     * Find the role with the given id.
     *
     * @param int|string $id
     * @return object|null
     */
    public function findRole(int|string $id): object|null
    {
        return $this->roles()->where('id', $id)->orWhere('name', $id)->first();
    }

    /**
     * Adds a new group to the team
     *
     * @param string $code
     * @param string $name
     * @return object
     */
    public function addGroup(string $code, string $name): object
    {
        return DB::transaction(function () use ($code, $name) {

            $group = $this->groups()->firstWhere('code', $code);

            if ($group) {
                throw new RuntimeException("Group with code '{$code}' already exists.");
            }

            return $this->groups()->create([
                'code' => $code,
                'name' => $name,
            ]);
        });
    }

    /**
     * Removes a group from a team
     *
     * @param string $code
     * @return bool
     */
    public function deleteGroup(string $code): bool
    {
        return DB::transaction(function () use ($code) {

            $group = $this->groups()->firstWhere('code', $code);

            if (!$group) {
                throw new ModelNotFoundException("Group with code '{$code}' not found.");
            }

            return $group->delete();
        });
    }

    /**
     * Get team group by its code
     *
     * @param string $code
     * @return object|null
     */
    public function group(string $code): object|null
    {
        return $this->groups()->where('code', $code)->first();
    }

    /**
     * @param array $capabilities
     * @return array
     */
    protected function getCapabilityIds(array $capabilities): array
    {
        return array_map(static fn($capability) => (Teams::$capabilityModel)::firstOrCreate(['code' => $capability])->id, $capabilities);
    }

    /**
     * Remove the given user from the team.
     *
     * @param object $user
     * @return void
     */
    public function deleteUser(object $user): void
    {
        DB::transaction(function () use ($user) {
            $this->users()->detach($user);
        });
    }

    /**
     * Purge all the team's resources.
     * @return void
     */
    public function purge(): void
    {
        DB::transaction(function () {
            $this->users()->detach();
            $this->delete();
        });
    }
}
