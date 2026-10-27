<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Database\Factories\GroupFactory;
use Jurager\Teams\Events\GroupMembersUpdated;
use Jurager\Teams\Support\Facades\Teams;
use Jurager\Teams\Traits\HasPermissions;

class Group extends Model
{
    use HasFactory;
    use HasPermissions;

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
     * Get the team that the group belongs to. Global groups have none.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get the users in the group.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('user'), Config::get('teams.tables.group_user'), 'group_id', 'user_id');
    }

    /**
     * Add users to the group. Users outside the group's team are skipped.
     */
    public function add(Model|Collection $users): static
    {
        $users = Collection::wrap($users);

        $this->getConnection()->transaction(function () use ($users) {

            $eligible = $this->eligible($users);

            $this->lockForUpdate()->findOrFail($this->getKey());

            $changes = $this->members()->syncWithoutDetaching($eligible->modelKeys());
            $this->forgetMembers($users);

            if ($changes['attached'] !== []) {
                GroupMembersUpdated::dispatch($this, $changes['attached'], []);
            }
        });

        return $this;
    }

    /**
     * Remove users from the group.
     */
    public function remove(Model|Collection $users): static
    {
        $users = Collection::wrap($users);

        $this->getConnection()->transaction(function () use ($users) {

            $this->lockForUpdate()->findOrFail($this->getKey());

            $members = $this->members();
            $ids = $members->whereKey($users->modelKeys())->pluck($members->getRelated()->getQualifiedKeyName())->all();

            $this->members()->detach($ids);
            $this->forgetMembers($users);

            if ($ids !== []) {
                GroupMembersUpdated::dispatch($this, [], $ids);
            }
        });

        return $this;
    }

    /**
     * Delete the group with its permissions and rules.
     */
    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(fn () => parent::delete());
    }

    /**
     * Keep the team owner and members among the users. A global group accepts anyone.
     */
    protected function eligible(Collection $users): Collection
    {
        if ($this->getAttribute(Config::get('teams.foreign_keys.team_id')) === null) {
            return $users;
        }

        $team = $this->team()->lockForUpdate()->firstOrFail();
        $members = $team->members();

        $ids = $members->whereKey($users->modelKeys())
            ->pluck($members->getRelated()->getQualifiedKeyName())
            ->push($team->user_id);

        return $users->whereIn($members->getRelated()->getKeyName(), $ids);
    }

    /**
     * Clear the loaded membership relations on the group and the users.
     */
    protected function forgetMembers(Collection $users): static
    {
        $users->each->unsetRelation('groups');

        return $this->unsetRelation('members');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): GroupFactory
    {
        return GroupFactory::new();
    }
}
