<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

class Group extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['code', 'name'];

    /**
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->mergeFillable([Config::get('teams.foreign_keys.team_id')]);

        parent::__construct($attributes);
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->table ?? Config::get('teams.tables.groups', 'groups');
    }

    /**
     * Get the team that the group belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get all the users that belong to the group.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(TeamsFacade::model('user'), Config::get('teams.tables.group_user'), 'group_id', 'user_id');
    }

    /**
     * Get all the permissions assigned to the group.
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(TeamsFacade::model('permission'), 'entity', Config::get('teams.tables.entity_permission'), 'entity_id', 'permission_id');
    }

    /**
     * Get all the abilities assigned to the group.
     */
    public function abilities(): MorphToMany
    {
        return $this->morphToMany(TeamsFacade::model('ability'), 'entity', Config::get('teams.tables.entity_ability'), 'entity_id', 'ability_id')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Attach the given users to the group, skipping users outside the group's team.
     */
    public function attachUser(Collection|Model $user): bool
    {
        $users = Collection::wrap($user);

        if ($users->isEmpty()) {
            return false;
        }

        return $this->getConnection()->transaction(function () use ($users) {
            $users = $this->eligibleMembers($users);

            $this->lockForUpdate()->findOrFail($this->getKey());

            $changes = $this->users()->syncWithoutDetaching($users->modelKeys());

            $this->forgetMembershipRelations($users);

            return $changes['attached'] !== [];
        });
    }

    /**
     * Detach the given users from the group.
     */
    public function detachUser(Collection|Model $user): bool
    {
        $users = Collection::wrap($user);

        if ($users->isEmpty()) {
            return false;
        }

        $detached = $this->users()->detach($users->modelKeys());

        $this->forgetMembershipRelations($users);

        if ($this->getConnection()->transactionLevel() === 0) {
            TeamsFacade::forgetCachedPermissions();
        }

        return $detached > 0;
    }

    /**
     * Keep only the team owner and members among the given users.
     */
    protected function eligibleMembers(Collection $users): Collection
    {
        if ($this->getAttribute(Config::get('teams.foreign_keys.team_id')) === null) {
            return $users;
        }

        $team = $this->team()->lockForUpdate()->firstOrFail();
        $members = $team->users();
        $userModel = $members->getRelated();

        $memberIds = $members->whereKey($users->modelKeys())
            ->pluck($userModel->getQualifiedKeyName())
            ->push($team->user_id);

        return $users->whereIn($userModel->getKeyName(), $memberIds);
    }

    /**
     * Clear the loaded group relations on the group and the given users.
     */
    protected function forgetMembershipRelations(Collection $users): void
    {
        $this->unsetRelation('users');

        $users->each->unsetRelation('groups');
    }
}
