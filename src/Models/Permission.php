<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Permission extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['name', 'code'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

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
        return $this->table ?? Config::get('teams.tables.permissions', 'permissions');
    }

    /**
     * Get the IDs of the permission codes in the team, creating missing ones.
     *
     * A null team holds the permissions of global groups.
     *
     * @param  list<string>  $codes
     * @return list<int|string>
     */
    public static function ids(int|string|null $team, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $team = [Config::get('teams.foreign_keys.team_id') => $team];
        $codes = array_unique($codes);

        $existing = static::where($team)->whereIn('code', $codes)->pluck('code')->all();
        $missing = array_diff($codes, $existing);

        // Skips codes that a concurrent request has just created.
        static::insertOrIgnore(array_map(fn (string $code) => [...$team, 'code' => $code], $missing));

        return static::where($team)->whereIn('code', $codes)->get()->modelKeys();
    }
}
