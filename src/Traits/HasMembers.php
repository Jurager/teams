<?php

namespace Jurager\Teams\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Jurager\Teams\Events\AddingTeamMember;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberRemoved;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Models\Owner;
use Jurager\Teams\Support\Facades\Teams;

trait HasMembers
{
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
        return $this->belongsToMany(Teams::model('user'), Teams::model('membership'), Config::get('teams.foreign_keys.team_id', 'team_id'))
            ->withPivot('role_id')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Get all abilities linked to the team.
     *
     * @return HasMany
     */
    public function abilities(): HasMany
    {
        return $this->hasMany(Teams::model('ability'), Config::get('teams.foreign_keys.team_id', 'team_id'),'id');
    }

    /**
     * Get all roles associated with the team.
     *
     * @return HasMany
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Teams::model('role'), Config::get('teams.foreign_keys.team_id', 'team_id'),'id');
    }

    /**
     * Get all groups associated with the team.
     *
     * @return HasMany
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Teams::model('group'), Config::get('teams.foreign_keys.team_id', 'team_id'),'id');
    }

    /**
     * Get all pending invitations for the team.
     *
     * @return HasMany
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Teams::model('invitation'), Config::get('teams.foreign_keys.team_id', 'team_id'),'id');
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
     * @param string $role_keyword The role ID or code that will be assigned to the user within the team.
     *
     * @return void
     */
    public function addUser(object $user, string $role_keyword): void
    {
        if ($user->id === $this->owner->id) {
            throw new RuntimeException(__('Owner already belongs to the team.'));
        }

        if ($this->hasUser($user)) {
            throw new RuntimeException(__('User already belongs to the team.'));
        }

        $role = $this->getRole($role_keyword);

        if (!$role) {
            throw new RuntimeException(__('Unable to find a role :role within team.', ['role' => $role_keyword]));
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
     * @param object $user The user model instance to be updated in the team.
     * @param string $role_keyword The role ID or code that will be assigned to the user within the team.
     * @return void
     */
    public function updateUser(object $user, string $role_keyword): void
    {
        if ($user->id === $this->owner->id) {
            throw new RuntimeException(__('You may not change the team owner.'));
        }

        if (!$this->hasUser($user)) {
            throw new RuntimeException(__('User not belongs to the team.'));
        }

        $role = $this->getRole($role_keyword);

        if (!$role) {
            throw new RuntimeException(__('Unable to find a role :role within team.', ['role' => $role_keyword]));
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
            throw new RuntimeException(__('You may not remove the team owner.'));
        }

        if (!$this->hasUser($user)) {
            throw new RuntimeException(__('User not belongs to the team.'));
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
     * @param  string|array $permissions
     * @param  bool         $require
     * @return bool
     */
    public function userHasPermission(object $user, string|array $permissions, bool $require = false): bool
    {
        return $user->hasTeamPermission($this, $permissions, $require);
    }

    /**
     * Check if the team has a specific role by ID or code or any roles at all
     *
     * @param string|null $keyword The role ID or code to check for. If null, checks for any roles.
     * @return bool
     */
    public function hasRole(int|string|null $keyword = null): bool
    {
        if ($keyword === null) {
            return $this->roles()->exists();
        }

        return $this->roles()->where((is_numeric($keyword) ? 'id' : 'code'), $keyword)->exists();
    }

    /**
     * Retrieves a role by its ID or code.
     *
     * @param int|string $keyword The ID or code of the role to search for.
     * @return object|null
     */
    public function getRole(int|string $keyword): object|null
    {
        return $this->roles()->firstWhere((is_numeric($keyword) ? 'id' : 'code'), $keyword);
    }

    /**
     * Add a role to the team with specific permissions.
     *
     * @param string $code Unique identifier for the role, used for retrieval and management.
     * @param array $permissions List of permissions codes to associate with this role.
     * @param string|null $name Optional name for the role. Defaults to a formatted version of `$code` if not provided.
     * @param string|null $description Optional description for the role to provide additional context.
     * @return object
     */
    public function addRole(string $code, array $permissions, string|null $name = null, string|null $description = null): object
    {
        if ($this->hasRole($code)) {
            throw new RuntimeException("Role with code '$code' already exists.");
        }

        $role = $this->roles()->create([
            'code' => $code,
            'name' => $name ?? Str::studly($code),
            'description' => $description
        ]);

        $role->permissions()->sync($this->getPermissionIds($permissions));

        return $role;
    }

    /**
     * Update an existing role with new permissions.
     *
     * @param int|string  $keyword The role ID or code to update
     * @param array $permissions An array of permissions codes to assign to the role.
     * @param string|null $name Optional name for the role. Defaults to a formatted version of `$code` if not provided.
     * @param string|null $description Optional description for the role to provide additional context.
     * @return object|bool
     */
    public function updateRole(int|string $keyword, array $permissions, string|null $name = null, string|null $description = null): object|bool
    {
        $role = $this->getRole($keyword);

        // Throw an exception if the role is not found
        if (!$role) {
            throw new ModelNotFoundException("Role with id/code '$keyword' not found.");
        }

        $role->update([
            'name' => $name ?? $role->name,
            'description' => $description ?? $role->description
        ]);

        $role->permissions()->sync($this->getPermissionIds($permissions));

        return $role;
    }

    /**
     * Delete a role from the team.
     *
     * @param  int|string $keyword The role ID or code to delete
     * @return bool
     */
    public function deleteRole(int|string $keyword): bool
    {
        $role = $this->getRole($keyword);

        if (!$role) {
            throw new ModelNotFoundException("Role with id/code '$keyword' not found.");
        }

        return $role->delete();
    }

    /**
     * Check if the team has a specific group by ID or code or any groups at all
     *
     * @param string|null $keyword The role ID or code to check for. If null, checks for any groups.
     * @return bool
     */
    public function hasGroup(int|string|null $keyword = null): bool
    {
        if ($keyword === null) {
            return $this->groups()->exists();
        }

        return $this->groups()->where((is_numeric($keyword) ? 'id' : 'code'), $keyword)->exists();
    }

    /**
     * Get a group by id or code.
     *
     * @param int|string $keyword The ID or code of the role to search for.
     * @return object|null
     */
    public function getGroup(int|string $keyword): object|null
    {
        return $this->groups()->firstWhere((is_numeric($keyword) ? 'id' : 'code'), $keyword);
    }

    /**
     * Add a new group to the team.
     *
     * @param string $code The unique code of the group.
     * @param array $permissions An array of permissions codes to assign to the group.
     * @param string|null $name Optional name for the group. Defaults to a formatted version of `$code` if not provided.
     * @return object
     */
    public function addGroup(string $code, array $permissions = [], string|null $name = null): object
    {
        if ($this->hasGroup($code)) {
            throw new RuntimeException("Group with code '$code' already exists.");
        }

        $group = $this->groups()->create([
            'code' => $code,
            'name' => $name ?? Str::studly($code)
        ]);

        $group->permissions()->sync($this->getPermissionIds($permissions));

        return $group;
    }

    /**
     * Update an existing group with new permissions.
     *
     * @param int|string $keyword The group ID or code to update
     * @param array $permissions An array of permissions codes to assign to the group.
     * @param string|null $name Optional name for the group. Defaults to a formatted version of `$code` if not provided.
     * @return object|bool
     */
    public function updateGroup(int|string $keyword, array $permissions = [], string|null $name = null): object|bool
    {
        // Fetch the group by ID or code
        $group = $this->getGroup($keyword);

        // Throw an exception if the group is not found
        if (!$group) {
            throw new ModelNotFoundException("Group with id/code '$keyword' not found.");
        }

        $group->update([
            'name' => $name ?? $group->name
        ]);

        $group->permissions()->sync($this->getPermissionIds($permissions));

        return $group;
    }

    /**
     * Remove a group from the team by code.
     *
     * @param  int|string  $keyword The ID or code of the group to delete.
     * @return bool
     */
    public function deleteGroup(int|string $keyword): bool
    {
        $group = $this->getGroup($keyword);

        if (!$group) {
            throw new ModelNotFoundException("Group with id/code '$keyword' not found.");
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
     * Get permissions IDs for a list of permissions.
     *
     * @param  array  $codes An array of permission codes to retrieve or create IDs for.
     * @return array
     */
    public function getPermissionIds(array $codes): array
    {
        $teamIdField = Config::get('teams.foreign_keys.team_id', 'team_id');

        $permissions = Teams::model('permission')::query()
            ->where($teamIdField, $this->id)
            ->whereIn('code', $codes)
            ->pluck('id', 'code')
            ->all();

        $newPermissions = array_diff($codes, array_keys($permissions));

        if (!empty($newPermissions)) {

            $items = array_map(fn($code) => [$teamIdField => $this->id ,'code' => $code], $newPermissions);

            Teams::model('permission')::query()->insert($items);

            $permissions = Teams::model('permission')::query()
                ->where($teamIdField, $this->id)
                ->whereIn('code', $codes)
                ->pluck('id', 'code')
                ->all();

        }

        return array_values($permissions);

    }
}