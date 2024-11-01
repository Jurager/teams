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
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::model('team'));
    }

    /**
     * Get all group users
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('user'), 'user_group', 'group_id', 'user_id');
    }

    /**
     * Get the capabilities that belongs to team.
     */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('capability'), 'group_capability');
    }

    /**
     * Attach user or users to a group
     */
    public function attachUser(Collection|Model $user): bool
    {
        // When a collection of users is received.
        if ($user instanceof Collection) {

            // Reject users not in the current team.
            $user = $user->reject(fn ($item) => ! $this->team->hasUser($item));

            // After sorting, ensure that there are no empty elements.
            return $user->isNotEmpty() && count($this->users()->sync($user, false));
        }

        // When a single user model is received
        if ($user::class === Teams::model('user')
            && $this->team->hasUser($user)
            && count($this->users()->syncWithoutDetaching($user))
        ) {
            return true;
        }

        return false;
    }

    /**
     * Detach user or users from group
     */
    public function detachUser(Collection|Model $user): bool
    {
        // When a collection of users is received.
        if ($user instanceof Collection) {
            // Filter out users not in the current team.
            $users_to_remove = $user->filter(fn ($item) => $this->team->hasUser($item));

            // Detach only if there are users to remove.
            return $users_to_remove->isNotEmpty() && $this->users()->detach($users_to_remove->pluck('id')->all());
        }

        // When a single user model is received
        if ($this->team->hasUser($user)) {
            return $this->users()->detach($user->id);
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
