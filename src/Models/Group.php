<?php

namespace Jurager\Teams\Models;

use Jurager\Teams\Teams;
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
     * @param $user
     */
    public function attachUser($user)
    {
        if ($this->team->hasUser($user)) {
            $this->users()->attach($user->id);
        }
    }

    /**
     * @param $user
     */
    public function detachUser($user)
    {
        $this->users()->detach($user->id);
    }
}
