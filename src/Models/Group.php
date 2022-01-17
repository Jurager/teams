<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Jurager\Teams\Teams;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class Group extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 'team_id', 'name' ];

    public $timestamps = false;
    /**
     * Get the team that the ability belongs to.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::teamModel());
    }

    /**
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Teams::$userModel, 'user_group', 'group_id', 'user_id');
    }

    /**
     * @param  object  $users
     * @return array|bool
     */
    public function attachUser(object $users): array|bool
    {
        if ($users instanceof Collection) {

            // Exclude from the collection users who are not in the current team
            //
            $users = $users->reject(fn ($user) => !$this->team->hasUser($user));

            // After sorting, checking for emptiness
            //
            return $users->isNotEmpty() ? $this->users()->sync($users, false) : false;
        }

        if ($users::class == Teams::$userModel) {
            if ($this->team->hasUser($users)) {
                return $this->users()->sync($users, false);
            }
        }

        return false;
    }

    /**
     * @param  object|array  $users
     * @return int
     */
    public function detachUser(object|array $users): int
    {
        if (is_array($users)) {
            return $this->users()->detach($users);
        }
        return $this->users()->detach($users->id);
    }

}
