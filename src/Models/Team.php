<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Jurager\Teams\Owner;
use Jurager\Teams\Teams;

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
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Teams::$userModel, 'user_id');
    }

    /**
     * Get all users of the team.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$userModel, Teams::$membershipModel)
            ->withPivot('role_id')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Get all the team's users including its owner.
     */
    public function allUsers(): Collection
    {
        return $this->users->merge([$this->owner]);
    }

    /**
     * Get all the abilities belong to the team.
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
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Teams::$roleModel);
    }

    /**
     * Get all groups of the team.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Teams::$groupModel);
    }

    /**
     * Get team group by its code
     */
    public function group(string $code): ?object
    {
        return $this->groups->firstWhere('code', $code);
    }

    /**
     * Check if the team has registered roles
     */
    public function hasRoles(): bool
    {
        return count($this->roles) > 0;
    }

    public function addRole(string $name, array $capabilities): object
    {
        $role = $this->roles()->create(['name' => $name]);

        $capability_ids = [];

        foreach ($capabilities as $capability) {

            $capability_ids[] = (Teams::$capabilityModel)::firstOrCreate(['code' => $capability])->id;
        }

        $role->capabilities()->attach($capability_ids);

        return $role;
    }

    public function updateRole(string $name, array $capabilities): object|bool
    {
        $role = $this->roles()->firstWhere('name', $name);

        if ($role) {

            $capability_ids = [];

            foreach ($capabilities as $capability) {

                $capability_ids[] = (Teams::$capabilityModel)::firstOrCreate(['code' => $capability])->id;
            }

            $role->capabilities()->sync($capability_ids);

            return $role;
        }

        return false;
    }

    /**
     * Deletes the given role from team
     */
    public function deleteRole(string $name): bool
    {
        $role = $this->roles()->firstWhere('name', $name);

        if ($role) {
            return $this->roles()->delete($role);
        }

        return false;
    }

    /**
     * Adds a new group to the team
     */
    public function addGroup(string $code, string $name): object
    {
        return $this->groups()->create(['code' => $code, 'name' => $name]);
    }

    /**
     * Removes a group from a team
     *
     * @param  string  $name
     */
    public function deleteGroup(string $code): object|bool
    {
        $group = $this->groups->firstWhere('code', $code);

        if ($group) {
            return $this->groups()->delete($group);
        }

        return false;
    }

    /**
     * Find the role with the given id.
     */
    public function findRole(int|string $id): ?object
    {
        return $this->roles->filter(fn ($role) => $role->id === $id || $role->name === $id)->first();
    }

    public function userRole(object $user): ?object
    {
        if ($this->owner === $user) {
            return new Owner;
        }

        if (! $this->hasUser($user)) {
            return null;
        }

        return $this->findRole($this->users->where('id', $user->id)->first()->membership->role->id);
    }

    /**
     * Determine if the given user belongs to the team.
     */
    public function hasUser(object $user): bool
    {
        return $this->users->contains($user) || $user->ownsTeam($this);
    }

    /**
     * Determine if the given email address belongs to a user on the team.
     */
    public function hasUserWithEmail(string $email): bool
    {
        return $this->allUsers()->contains(static fn ($user) => $user->email === $email);
    }

    /**
     * Determine if the given user has the given permission on the team.
     */
    public function userHasPermission(object $user, string|array $permission, bool $require = false): bool
    {
        return $user->hasTeamPermission($this, $permission, $require);
    }

    /**
     * Get all the pending user invitations for the team.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Teams::$invitationModel);
    }

    /**
     * Remove the given user from the team.
     */
    public function deleteUser(object $user): void
    {
        $this->users()->detach($user);
    }

    /**
     * Purge all the team's resources.
     */
    public function purge(): void
    {
        $this->users()->detach();

        $this->delete();
    }
}
