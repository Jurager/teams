<?php

namespace Jurager\Teams\Models;

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
     * Create a new Team model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('teams.tables.teams');
    }

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
     * Get all users associated with the team.
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$userModel, Teams::$membershipModel, config('teams.foreign_keys.team_id'))
            ->withPivot('role_id')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Get all abilities linked to the team.
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
     * Get all roles associated with the team.
     *
     * @return HasMany
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Teams::$roleModel, config('teams.foreign_keys.team_id'),'id');
    }

    /**
     * Get all groups associated with the team.
     *
     * @return HasMany
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Teams::$groupModel, config('teams.foreign_keys.team_id'),'id');
    }

    /**
     * Get all pending invitations for the team.
     *
     * @return HasMany
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Teams::$invitationModel, config('teams.foreign_keys.team_id'),'id');
    }

    /**
     * Retrieve all users in the team, including the owner.
     *
     * @return Collection
     */
    public function allUsers(): Collection
    {
        return $this->users->merge([$this->owner]);
    }

    /**
     * Check if the team includes a given user.
     *
     * @param  object  $user
     * @return bool
     */
    public function hasUser(object $user): bool
    {
        return $this->users->contains($user) || $user->ownsTeam($this);
    }

    /**
     * Check if the team includes a user with a specific email.
     *
     * @param  string  $email
     * @return bool
     */
    public function hasUserWithEmail(string $email): bool
    {
        return $this->allUsers()->contains(fn($user) => $user->email === $email);
    }

    /**
     * Get the role of a specific user within the team.
     *
     * @param  object  $user
     * @return object|null
     */
    public function userRole(object $user): object|null
    {
        return $this->owner === $user ? new Owner : $this->findRole($this->users->where('id', $user->id)->first()->membership->role->id ?? null);
    }

    /**
     * Check if a user has a specific permission in the team.
     *
     * @param  object       $user
     * @param  string|array $permission
     * @param  bool         $require
     * @return bool
     */
    public function userHasPermission(object $user, string|array $permission, bool $require = false): bool
    {
        return $user->hasTeamPermission($this, $permission, $require);
    }

    /**
     * Check if the team has any roles.
     *
     * @return bool
     */
    public function hasRoles(): bool
    {
        return $this->roles()->exists();
    }

    /**
     * Add a role to the team with specific capabilities.
     *
     * @param  string  $code
     * @param  array   $capabilities
     * @return object
     */
    public function addRole(string $code, array $capabilities): object
    {
        return \DB::transaction(function () use ($code, $capabilities) {

            $role = $this->roles()->firstWhere('code', $code);

            if ($role) {
                throw new RuntimeException("Role with code '{$code}' already exists.");
            }

            $role = $this->roles()->create(['code' => $code]);

            $capabilityIds = $this->getCapabilityIds($capabilities);

            if (!empty($capabilityIds)) {
                $role->capabilities()->sync($capabilityIds);
            }

            return $role;
        });
    }

    /**
     * Update an existing role with new capabilities.
     *
     * @param  string  $code
     * @param  array   $capabilities
     * @return object|bool
     */
    public function updateRole(string $code, array $capabilities): object|bool
    {
        return DB::transaction(function () use ($code, $capabilities) {

            $role = $this->roles()->firstWhere('code', $code);

            if (!$role) {
                throw new ModelNotFoundException("Role with code '{$code}' not found.");
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
     * Delete a role from the team.
     *
     * @param  string  $code
     * @return bool
     */
    public function deleteRole(string $code): bool
    {
        return DB::transaction(function () use ($code) {

            $role = $this->roles()->firstWhere('code', $code);

            if (!$role) {
                throw new ModelNotFoundException("Role with code '{$code}' not found.");
            }

            return $role->delete();
        });
    }

    /**
     * Find a role by ID or name.
     *
     * @param  int|string  $id
     * @return object|null
     */
    public function findRole(int|string $id): object|null
    {
        return $this->roles()->where('id', $id)->orWhere('name', $id)->first();
    }

    /**
     * Add a new group to the team.
     *
     * @param  string  $code
     * @param  string  $name
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
     * Remove a group from the team by code.
     *
     * @param  string  $code
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
     * Retrieve a group by its code.
     *
     * @param  string  $code
     * @return object|null
     */
    public function group(string $code): object|null
    {
        return $this->groups()->where('code', $code)->first();
    }

    /**
     * Get capability IDs for a list of capabilities.
     *
     * @param  array  $capabilities
     * @return array
     */
    protected function getCapabilityIds(array $capabilities): array
    {
        return array_map(static fn($capability) => (Teams::$capabilityModel)::firstOrCreate(['code' => $capability])->id, $capabilities);
    }

    /**
     * Remove a user from the team.
     *
     * @param  object  $user
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
     *
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
