<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;
use Exception;

class Role extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['code', 'name', 'description'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [
        'permissions',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
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

        static::deleting(static function ($role) {
            $role->permissions()->detach();
            $role->abilities()->detach();
        });

    }

    /**
     * Get the team that the role belongs to.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('team'));
    }

    /**
     * Get the permissions that belongs to role.
     *
     * @return MorphToMany
     */
    public function permissions(): MorphToMany
    {
        return $this->morphToMany(TeamsFacade::model('permission'), 'entity', 'entity_permission');
    }

    /**
     * Get the abilities that belongs to role.
     *
     * @return MorphToMany
     */
    public function abilities(): MorphToMany
    {
        return $this->morphToMany(TeamsFacade::model('ability'), 'entity', 'entity_ability')
            ->withPivot('forbidden')
            ->withTimestamps();
    }

    /**
     * Get all users associated with the role.
     *
     * @return BelongsToMany
     * @throws Exception
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(TeamsFacade::model('team'), Config::get('teams.tables.team_user', 'team_user'), 'role_id', Config::get('teams.foreign_keys.team_id', 'team_id'));
    }
}
