<?php

namespace Jurager\Teams\Models;

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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function team()
    {
        return $this->belongsTo(Teams::teamModel());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
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
            // Исключаем из коллекции пользователей которые не в текущей команде
            //
            $users = $users->reject(fn ($user) => !$this->team->hasUser($user));

            // После сортировки проверяем на пустоту
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
