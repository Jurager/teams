<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Jurager\Teams\Events\AddingTeamMember;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberRemoved;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Rules\Role;
use Jurager\Teams\Support\Facades\Teams;
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
        return $this->belongsTo(Teams::model('user'), 'user_id');
    }

    /**
     * Get all users associated with the team.
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('user'), Teams::model('membership'), config('teams.foreign_keys.team_id'))
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
        return $this->belongsToMany(Teams::model('ability'), Teams::model('permission'))
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
        return $this->hasMany(Teams::model('role'), config('teams.foreign_keys.team_id'),'id');
    }

    /**
     * Get all groups associated with the team.
     *
     * @return HasMany
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Teams::model('group'), config('teams.foreign_keys.team_id'),'id');
    }

    /**
     * Get all pending invitations for the team.
     *
     * @return HasMany
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Teams::model('invitation'), config('teams.foreign_keys.team_id'),'id');
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
     * Adds a user to the team with a specified role.
     *
     * @param object $user The user model instance to be added to the team.
     * @param string $role_code The role object that will be assigned to the user within the team.
     *
     * @return void
     */
    public function addUser(object $user, string $role_code): void
    {
        if ($this->hasUser($user)) {
            throw new RuntimeException(
                __('User already belongs to the team.')
            );
        }

        if (! $role = $this->getRole($role_code)) {
            throw new RuntimeException(
                __('We were unable to find a role :role within team.', ['role' => $role])
            );
        }

        // Dispatch an event before attaching the user
        AddingTeamMember::dispatch($this, $user);

        // Attach the user to the team
        $this->users()->attach($user, ['role_id' => $role->id]);

        // Dispatch an event after user is added to the team
        TeamMemberAdded::dispatch($this->fresh(), $user);
    }

    /**
     * Update the role of a specific user within the team.
     *
     * @param object $user
     * @param string $role_code
     * @return void
     */
    public function updateUser(object $user, string $role_code): void
    {
        if ($user->id === $this->owner->id) {
            throw new RuntimeException(
                __('You may not change role of the team owner.')
            );
        }

        if (! $role = $this->getRole($role_code)) {
            throw new RuntimeException(
                __('We were unable to find a role :role within team.', ['role' => $role])
            );
        }

        // Update the user role for the team
        $this->users()->updateExistingPivot($user->id, ['role_id' => $role->id]);

        // Dispatch event after updating the user role
        TeamMemberUpdated::dispatch($this->fresh(), $user->fresh());
    }

    /**
     * Remove a user from the team.
     *
     * @param object $user The user instance to remove from the team.
     *
     * @return void
     */
    public function deleteUser(object $user): void
    {
        if ($user->id === $this->owner->id) {
            throw new RuntimeException(
                __('You may not remove the team owner.')
            );
        }

        // Detach the user from the team
        $this->users()->detach($user->id);

        // Dispatch event after removing the user
        TeamMemberRemoved::dispatch($this->fresh(), $user);
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
        return $this->owner === $user ? new Owner : $this->getRole($this->users->firstWhere('id', $user->id)->membership->role->id ?? null);
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
     * Check if the team has a specific role or any roles at all.
     *
     * @param string|null $code The role code to check for. If null, checks for any roles.
     * @return bool
     */
    public function hasRole(string|null $code = null): bool
    {
        $roles = $this->roles();

        if ($code !== null) {
            $roles->where('code', $code);
        }

        return $roles->exists();
    }

    /**
     * Retrieves a role by its ID or code.
     *
     * @param int|string|null $keyword The ID or code of the role to search for.
     * @return object|null
     */
    public function getRole(int|string|null $keyword): object|null
    {
        return $this->roles()->firstWhere(function ($query) use ($keyword) {
            $query->where('id',  $keyword)
                ->orWhere('code', $keyword);
        });
    }

    /**
     * Add a role to the team with specific capabilities.
     *
     * @param string $code Unique identifier for the role, used for retrieval and management.
     * @param array $capabilities List of capability codes to associate with this role.
     * @param string|null $name Optional name for the role. Defaults to a formatted version of `$code` if not provided.
     * @param string|null $description Optional description for the role to provide additional context.
     * @return object
     */
    public function addRole(string $code, array $capabilities, string|null $name = null, string|null $description = null): object
    {
        if ($this->roles()->where('code', $code)->exists()) {
            throw new RuntimeException("Role with code '$code' already exists.");
        }

        $role = $this->roles()->create([
            'code' => $code,
            'name' => $name ?? Str::studly($code),
            'description' => $description
        ]);

        $capabilityIds = $this->getCapabilityIds($capabilities);

        if (!empty($capabilityIds)) {
            $role->capabilities()->sync($capabilityIds);
        }

        return $role;
    }

    /**
     * Update an existing role with new capabilities.
     *
     * @param  string  $code The unique code of the role to update.
     * @param  array   $capabilities An array of capability codes to assign to the role.
     * @return object|bool
     */
    public function updateRole(string $code, array $capabilities): object|bool
    {
        $role = $this->roles()->firstWhere('code', $code);

        if (!$role) {
            throw new ModelNotFoundException("Role with code '$code' not found.");
        }

        $capability_ids = $this->getCapabilityIds($capabilities);

        if (!empty($capability_ids)) {
            $role->capabilities()->sync($capability_ids);
        } else {
            $role->capabilities()->detach();
        }

        return $role;
    }

    /**
     * Delete a role from the team.
     *
     * @param  string $code The unique code of the role to delete
     * @return bool
     */
    public function deleteRole(string $code): bool
    {
        $role = $this->roles()->firstWhere('code', $code);

        if (!$role) {
            throw new ModelNotFoundException("Role with code '$code' not found.");
        }

        return $role->delete();
    }

    /**
     * Get a group by id or code.
     *
     * @param int|string $keyword The ID or code of the role to search for.
     * @return object|null
     */
    public function getGroup(int|string $keyword): object|null
    {
        return $this->groups()->firstWhere(function ($query) use ($keyword) {
            $query->where('id',  $keyword)
                ->orWhere('code', $keyword);
        });
    }

    /**
     * Add a new group to the team.
     *
     * @param  string  $code The unique code of the group.
     * @param  string  $name
     * @return object
     */
    public function addGroup(string $code, string $name): object
    {
        if ($this->groups()->where('code', $code)->exists()) {
            throw new RuntimeException("Group with code '$code' already exists.");
        }

        return $this->groups()->create(compact('code', 'name'));
    }

    /**
     * Remove a group from the team by code.
     *
     * @param  string  $code The unique code of the group to delete.
     * @return bool
     */
    public function deleteGroup(string $code): bool
    {
        $group = $this->groups()->firstWhere('code', $code);

        if (!$group) {
            throw new ModelNotFoundException("Group with code '$code' not found.");
        }

        return $group->delete();
    }

    /**
     * Purge all the team's resources.
     *
     * @return void
     */
    public function purge(): void
    {
        $this->users()->detach();
        $this->delete();
    }

    /**
     * Get capability IDs for a list of capabilities.
     *
     * @param  array  $codes An array of capability codes to retrieve or create IDs for.
     * @return array
     */
    protected function getCapabilityIds(array $codes): array
    {
        $capabilities = Teams::model('capability')::query()
            ->whereIn('code', $codes)
            ->pluck('id', 'code')
            ->all();

        $diff = array_diff($codes, array_keys($capabilities));

        if (!empty($diff)) {

            $items = array_map(static fn($code) => ['code' => $code], $diff);

            Teams::model('capability')::query()
                ->insert($items);

            $inserted = Teams::model('capability')::query()
                ->whereIn('code', $diff)
                ->pluck('id', 'code')
                ->all();

            return array_merge($capabilities, $inserted);
        }

        return [];
    }
}