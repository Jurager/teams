<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Jurager\Teams\Support\Facades\Teams;

class Group extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['code', 'name'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->fillable[] = config('teams.foreign_keys.team_id');
    }

    /**
     * Get the team that the group belongs to.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::model('team'));
    }

    /**
     * Get all group users
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('user'), 'user_group', 'group_id', 'user_id');
    }

    /**
     * Get the capabilities that belongs to team.
     *
     * @return BelongsToMany
     */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('capability'), 'group_capability');
    }

    /**
     * Attach user or users to a group
     *
     * @param Collection|Model $user
     * @return bool
     */
    public function attachUser(Collection|Model $user): bool
    {
        if ($user instanceof Collection) {
            $users = $user->filter(fn($item) => $this->team->hasUser($item));

            return $users->isNotEmpty() && count($this->users()->sync($users, false));
        }

        if ($user instanceof Model && $this->team->hasUser($user)) {
            return count($this->users()->syncWithoutDetaching($user));
        }

        return false;
    }

    /**
     * Detach user or users from group
     */
    public function detachUser(Collection|Model $user): bool
    {
        if ($user instanceof Collection) {

            $users = $user->filter(fn ($item) => $this->team->hasUser($item));

            return $users->isNotEmpty() && count($this->users()->detach($users->pluck('id')->all()));
        }

        if ($this->team->hasUser($user)) {
            return count($this->users()->detach($user->id));
        }

        return false;
    }

    /**
     * Get the permissions of all team capabilities.
     */
    public function getPermissionsAttribute(): array
    {
        return $this->capabilities->pluck('code')->all();
    }
}
