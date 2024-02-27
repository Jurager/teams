<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [ 'team_id', 'name' ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the team that the group belongs to.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::$teamModel);
    }

    /**
     * Get all group users
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$userModel, 'user_group', 'group_id', 'user_id');
    }

    /**
     * Get the capabilities that belongs to team.
     *
     * @return BelongsToMany
     */
    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$capabilityModel, 'group_capability');
    }

    /**
     * Attach user or users to a group
     *
     * @param Collection|Model $user
     * @return bool
     */
    public function attachUser(Collection|Model $user): bool
    {
        // When a collection of users is received.
        if ($user instanceof Collection) {

            // Reject users not in the current team.
            $user = $user->reject(fn ($item) => !$this->team->hasUser($item));

            // After sorting, ensure that there are no empty elements.
            if ($user->isNotEmpty() && count($this->users()->sync($user, false))) {
                return true;
            }

            return false;
        }

        // When a single user model is received
        if ($user::class === Teams::$userModel
            && $this->team->hasUser($user)
            && count($this->users()->syncWithoutDetaching($user))
        ) {
            return true;
        }

        return false;
    }

    /**
     * Detach user or users from group
     *
     * @param Collection|Model $user
     * @return bool
     */
    public function detachUser(Collection|Model $user): bool
    {
        // When a collection of users is received.
        if ($user instanceof Collection) {
            // Filter out users not in the current team.
            $users_to_remove = $user->filter(fn($item) => $this->team->hasUser($item));

            // Detach only if there are users to remove.
            return $users_to_remove->isNotEmpty() && $this->users()->detach($users_to_remove->pluck('id')->all());
        }

        // When a single user model is received
        if ($user instanceof Model && $this->team->hasUser($user)) {
            return $this->users()->detach($user->id);
        }

         return false;
    }

    /**
     * Get the permissions of all team capabilities.
     *
     * @return array
     */
    public function getPermissionsAttribute(): array
    {
        return $this->capabilities->pluck('code')->all();
    }

}
