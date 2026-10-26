<?php

namespace Jurager\Teams\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberAdding;
use Jurager\Teams\Events\TeamMemberInvited;
use Jurager\Teams\Events\TeamMemberInviting;
use Jurager\Teams\Events\TeamMemberRemoved;
use Jurager\Teams\Events\TeamMemberRemoving;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Mail\Invitation;
use Jurager\Teams\Models\Owner;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;
use RuntimeException;
use Throwable;

trait HasMembers
{
    /**
     * Get the owner of the team.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('user'), 'user_id');
    }

    /**
     * Get all the users that belong to the team.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(TeamsFacade::model('user'), TeamsFacade::model('membership'), Config::get('teams.foreign_keys.team_id'), 'user_id')
            ->withPivot('role_id')
            ->withTimestamps()
            ->as('membership');
    }

    /**
     * Get all the team's permissions.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(TeamsFacade::model('permission'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get all the team's abilities.
     */
    public function abilities(): HasMany
    {
        return $this->hasMany(TeamsFacade::model('ability'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get all the team's roles.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(TeamsFacade::model('role'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get all the team's groups.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(TeamsFacade::model('group'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get all the pending invitations for the team.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamsFacade::model('invitation'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get all the team's users including its owner.
     */
    public function allUsers(): Collection
    {
        return $this->users->merge(array_filter([$this->owner]));
    }

    /**
     * Determine if the given user belongs to the team.
     */
    public function hasUser(Model $user): bool
    {
        return $user->ownsTeam($this) || $this->users()->whereKey($user->getKey())->exists();
    }

    /**
     * Determine if a user with the given email belongs to the team.
     */
    public function hasUserWithEmail(string $email): bool
    {
        return $this->owner()->where('email', $email)->exists() || $this->users()->where('email', $email)->exists();
    }

    /**
     * Add a user to the team with the given role.
     */
    public function addUser(Model $user, int|string $roleKeyword): void
    {
        $this->getConnection()->transaction(function () use ($user, $roleKeyword) {
            $this->lockMemberships();

            throw_if($this->hasUser($user), RuntimeException::class, 'User already belongs to the team.');

            $role = $this->findOrFailByKeyword($this->roles(), $roleKeyword);

            TeamMemberAdding::dispatch($this, $user);

            $this->users()->attach($user, ['role_id' => $role->getKey()]);
            $this->forgetMemberRelations($user);

            TeamMemberAdded::dispatch($this, $user);
        });
    }

    /**
     * Change the role of the given team member.
     */
    public function updateUser(Model $user, int|string $roleKeyword): void
    {
        $this->getConnection()->transaction(function () use ($user, $roleKeyword) {
            $this->lockMemberships();
            $this->ensureMutableMember($user);

            $role = $this->findOrFailByKeyword($this->roles(), $roleKeyword);

            $this->users()->updateExistingPivot($user->getKey(), ['role_id' => $role->getKey()]);
            $this->forgetMemberRelations($user);

            TeamMemberUpdated::dispatch($this, $user);
        });
    }

    /**
     * Remove the given user from the team, along with their team groups and abilities.
     */
    public function deleteUser(Model $user): void
    {
        $this->getConnection()->transaction(function () use ($user) {
            $this->lockMemberships();
            $this->ensureMutableMember($user);

            TeamMemberRemoving::dispatch($this, $user);

            $groups = $this->groups();
            $abilities = $user->abilities();

            $user->groups()->detach($groups->pluck($groups->getRelated()->getKeyName()));

            $abilities->detach(
                $abilities->whereBelongsTo($this, 'team')
                    ->pluck($abilities->getRelated()->getQualifiedKeyName())
            );

            $this->users()->detach($user->getKey());
            $this->forgetMemberRelations($user);

            TeamMemberRemoved::dispatch($this, $user);
        });
    }

    /**
     * Get the role of the given user on the team.
     */
    public function userRole(Model $user): Model|Owner|null
    {
        return $user->teamRole($this);
    }

    /**
     * Determine if the given user has the given permissions on the team.
     */
    public function userHasPermission(Model $user, string|array $permissions, bool $require = false): bool
    {
        return $user->hasTeamPermission($this, $permissions, $require);
    }

    /**
     * Invite a user to the team by email.
     */
    public function inviteUser(string $email, int|string $keyword): void
    {
        $this->ensureInvitationsEnabled();

        $this->getConnection()->transaction(function () use ($email, $keyword) {
            $this->lockMemberships();

            throw_if($this->hasUserWithEmail($email), RuntimeException::class, 'This user already belongs to the team.');

            $role = $this->findOrFailByKeyword($this->roles(), $keyword);

            TeamMemberInviting::dispatch($this, $email, $role);

            $invitation = $this->invitations()->updateOrCreate(['email' => $email], ['role_id' => $role->getKey()]);

            TeamMemberInvited::dispatch($this, $email, $role);

            $this->getConnection()->afterCommit(fn () => Mail::to($email)->send(new Invitation($invitation)));
        });
    }

    /**
     * Accept the given invitation and add the invited user to the team.
     * @throws Throwable
     */
    public function inviteAccept(int|string $invitationId): void
    {
        $this->ensureInvitationsEnabled();

        $this->getConnection()->transaction(function () use ($invitationId) {
            $this->lockMemberships();

            $invitation = $this->invitations()->lockForUpdate()->findOrFail($invitationId);
            $user = $invitation->user ?? throw new RuntimeException('Invited user not found.');

            if (! $this->hasUser($user)) {
                $this->addUser($user, $invitation->role_id);
            }

            $invitation->delete();
        });
    }

    /**
     * Determine if the team has any role, or the role with the given id or code.
     */
    public function hasRole(int|string|null $keyword = null): bool
    {
        return $keyword === null
            ? $this->roles()->exists()
            : $this->whereKeyword($this->roles(), $keyword)->exists();
    }

    /**
     * Find a team role by id or code.
     */
    public function getRole(int|string $keyword): ?Model
    {
        return $this->whereKeyword($this->roles(), $keyword)->first();
    }

    /**
     * Create a new team role with the given permissions.
     */
    public function addRole(string $code, array $permissions, ?string $name = null, ?string $description = null): Model
    {
        return $this->getConnection()->transaction(function () use ($code, $permissions, $name, $description) {
            throw_if($this->roles()->where('code', $code)->exists(), RuntimeException::class, "Role with code '{$code}' already exists.");

            $role = $this->roles()->create([
                'code' => $code,
                'name' => $name ?? Str::studly($code),
                'description' => $description,
            ]);

            $role->permissions()->sync($this->getPermissionIds($permissions));

            $this->unsetRelation('roles');

            return $role;
        });
    }

    /**
     * Update the given team role. The description is only changed when passed explicitly.
     */
    public function updateRole(int|string $keyword, ?array $permissions = null, ?string $name = null, ?string $description = null): Model
    {
        $updateDescription = func_num_args() >= 4;

        return $this->getConnection()->transaction(function () use ($keyword, $permissions, $name, $description, $updateDescription) {
            $role = $this->findOrFailByKeyword($this->roles(), $keyword);

            $attributes = ['name' => $name ?? $role->name];

            if ($updateDescription) {
                $attributes['description'] = $description;
            }

            $role->update($attributes);

            if ($permissions !== null) {
                $role->permissions()->sync($this->getPermissionIds($permissions));
            }

            $this->unsetRelation('roles');

            return $role->unsetRelation('permissions');
        });
    }

    /**
     * Delete the given team role.
     */
    public function deleteRole(int|string $keyword): bool
    {
        $role = $this->findOrFailByKeyword($this->roles(), $keyword);

        return tap($role->deleteOrFail(), fn () => $this->unsetRelation('roles'));
    }

    /**
     * Determine if the team has any group, or the group with the given id or code.
     */
    public function hasGroup(int|string|null $keyword = null): bool
    {
        return $keyword === null
            ? $this->groups()->exists()
            : $this->whereKeyword($this->groups(), $keyword)->exists();
    }

    /**
     * Find a team group by id or code.
     */
    public function getGroup(int|string $keyword): ?Model
    {
        return $this->whereKeyword($this->groups(), $keyword)->first();
    }

    /**
     * Create a new team group with the given permissions.
     */
    public function addGroup(string $code, array $permissions = [], ?string $name = null): Model
    {
        return $this->getConnection()->transaction(function () use ($code, $permissions, $name) {
            throw_if($this->groups()->where('code', $code)->exists(), RuntimeException::class, "Group with code '{$code}' already exists.");

            $group = $this->groups()->create([
                'code' => $code,
                'name' => $name ?? Str::studly($code),
            ]);

            $group->permissions()->sync($this->getPermissionIds($permissions));

            $this->unsetRelation('groups');

            return $group;
        });
    }

    /**
     * Update the given team group.
     */
    public function updateGroup(int|string $keyword, ?array $permissions = null, ?string $name = null): Model
    {
        return $this->getConnection()->transaction(function () use ($keyword, $permissions, $name) {
            $group = $this->findOrFailByKeyword($this->groups(), $keyword);

            $group->update(['name' => $name ?? $group->name]);

            if ($permissions !== null) {
                $group->permissions()->sync($this->getPermissionIds($permissions));
            }

            $this->unsetRelation('groups');

            return $group->unsetRelation('permissions');
        });
    }

    /**
     * Delete the given team group.
     */
    public function deleteGroup(int|string $keyword): bool
    {
        $group = $this->findOrFailByKeyword($this->groups(), $keyword);

        return tap($group->deleteOrFail(), fn () => $this->unsetRelation('groups'));
    }

    /**
     * Resolve permission codes to ids, creating any that are missing.
     *
     * @param  list<string>  $codes
     * @return list<int|string>
     */
    public function getPermissionIds(array $codes): array
    {
        $codes = array_values(array_unique($codes));

        if ($codes === []) {
            return [];
        }

        $permissions = $this->permissions();
        $teamKey = $permissions->getForeignKeyName();

        $permissions->upsert(array_map(fn ($code) => [$teamKey => $this->getKey(), 'code' => $code], $codes), [$teamKey, 'code'], ['code']);

        return $permissions->whereIn('code', $codes)->pluck($permissions->getRelated()->getKeyName())->all();
    }

    /**
     * Remove all users from the team and delete it.
     */
    public function purge(): void
    {
        $this->getConnection()->transaction(function () {
            $this->users()->detach();

            $this->delete();
        });
    }

    /**
     * Constrain the relation to the record with the given id or code. Numeric keywords are ids.
     */
    protected function whereKeyword(HasMany $relation, int|string $keyword): HasMany
    {
        return $relation->where(is_numeric($keyword) ? $relation->getRelated()->getKeyName() : 'code', $keyword);
    }

    /**
     * Find the related record with the given id or code, or throw an exception.
     *
     * @throws ModelNotFoundException
     */
    protected function findOrFailByKeyword(HasMany $relation, int|string $keyword): Model
    {
        return $this->whereKeyword($relation, $keyword)->first()
            ?? throw (new ModelNotFoundException)->setModel($relation->getRelated()::class, [$keyword]);
    }

    /**
     * Lock the team row so membership changes are serialized.
     */
    protected function lockMemberships(): void
    {
        $this->lockForUpdate()->findOrFail($this->getKey());
    }

    /**
     * Ensure the given user is a team member other than the owner.
     * @throws Throwable
     */
    protected function ensureMutableMember(Model $user): void
    {
        throw_if($user->ownsTeam($this), RuntimeException::class, 'The team owner cannot be changed or removed.');
        throw_unless($this->hasUser($user), RuntimeException::class, 'User does not belong to the team.');
    }

    /**
     * Ensure team invitations are enabled.
     * @throws Throwable
     */
    protected function ensureInvitationsEnabled(): void
    {
        throw_unless(Config::get('teams.invitations.enabled'), RuntimeException::class, 'Team invitations are disabled.');
    }

    /**
     * Clear the cached membership relations on the team and the given user.
     */
    protected function forgetMemberRelations(Model $user): void
    {
        $this->unsetRelation('users');

        $user->unsetRelation('teams')->unsetRelation('groups')->unsetRelation('abilities');
    }
}
