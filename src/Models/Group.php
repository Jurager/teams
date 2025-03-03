<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Config;
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
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [
        'permissions',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->fillable[] = Config::get('teams.foreign_keys.team_id');
    }

    /**
     * Bootstrap any application services.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(static function ($group) {
            $group->permissions()->detach();
            $group->abilities()->detach();

        });
    }

    /**
     * Get the team that the group belongs to.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get all group users
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('user'), 'group_user', 'group_id', 'user_id');
    }

    /**
     * Get the permissions that belongs to group.
     *
     * @return MorphToMany
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(Teams::model('permission'), 'entity', 'entity_permission');
    }

    /**
     * Get the abilities that belongs to group.
     *
     * @return MorphToMany
     */
    public function abilities(): MorphToMany
    {
        return $this->morphToMany(Teams::model('ability'), 'entity', 'entity_ability')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Attach user or users to a group
     *
     * @param Collection|Model $user
     * @return bool
     */
    public function attachUser(Collection|Model $user): bool
    {
        // Convert user to collection if it is the only model
        $users = $user instanceof Collection ? $user : collect([$user]);

        // Filter only those users who are in the team
        $filteredUserIds = $users->filter(fn ($item) => $this->team->hasUser($item));

        // If there are users left after filtering, synchronize and return the result
        return $filteredUserIds->isNotEmpty() && $this->users()->syncWithoutDetaching($filteredUserIds->pluck('id')) > 0;
    }

    /**
     * Detach user or users from group
     */
    public function detachUser(Collection|Model $user): bool
    {
        // Convert user to collection if it is the only model
        $users = $user instanceof Collection ? $user : collect([$user]);

        // Filter only those users who are in the team
        $filteredUserIds  = $users->filter(fn ($item) => $this->team->hasUser($item));

        // If there are any users left after filtering, we execute detach and return the result
        return $filteredUserIds ->isNotEmpty() && $this->users()->detach($filteredUserIds->pluck('id')) > 0;
    }
}
