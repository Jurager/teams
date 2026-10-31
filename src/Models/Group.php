<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Database\Factories\GroupFactory;
use Jurager\Teams\Facades\Teams;
use Jurager\Teams\GroupMembers;
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
        (new GroupMembers($this))->add(Collection::wrap($users));

        return $this;
    }

    public function remove(Model|Collection $users): static
    {
        (new GroupMembers($this))->remove(Collection::wrap($users));

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
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): GroupFactory
    {
        return GroupFactory::new();
    }
}
