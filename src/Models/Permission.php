<?php

namespace Jurager\Teams\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

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
     * Name new permissions after their code.
     */
    protected static function booted(): void
    {
        static::creating(function (self $permission) {
            $permission->name ??= static::headline($permission->code);
        });
    }

    /**
     * Store enum codes as strings.
     */
    protected function code(): Attribute
    {
        return Attribute::set(fn (string|BackedEnum $code) => $code instanceof BackedEnum ? $code->value : $code);
    }

    /**
     * Get the IDs of the permissions in the team, creating missing ones.
     *
     * Accepts codes and code => name pairs; a given name replaces the stored one.
     * A null team holds the permissions of global groups.
     *
     * @param  array<int|string, string|BackedEnum>  $permissions
     * @return list<int|string>
     */
    public static function ids(int|string|null $team, array $permissions): array
    {
        $names = static::names($permissions);

        if ($names === []) {
            return [];
        }

        $team = [Config::get('teams.foreign_keys.team_id') => $team];
        $existing = static::where($team)->whereIn('code', array_keys($names))->get();

        foreach ($existing as $permission) {
            $permission->update(['name' => $names[$permission->code] ?? $permission->name]);
        }

        $missing = array_diff_key($names, $existing->keyBy('code')->all());

        if ($missing === []) {
            return $existing->modelKeys();
        }

        $rows = collect($missing)->map(fn (?string $name, string $code) => [
            ...$team, 'code' => $code, 'name' => $name ?? static::headline($code),
        ]);

        // Skips codes that a concurrent request has just created.
        static::insertOrIgnore($rows->values()->all());

        return static::where($team)->whereIn('code', array_keys($names))->get()->modelKeys();
    }

    /**
     * Turn codes and code => name pairs into a code => name map, with null for codes without a name.
     *
     * @param  array<int|string, string|BackedEnum>  $permissions
     * @return array<string, string|null>
     */
    protected static function names(array $permissions): array
    {
        $names = [];

        foreach ($permissions as $key => $value) {
            if (is_int($key)) {
                $names[$value instanceof BackedEnum ? $value->value : $value] ??= null;

                continue;
            }

            $names[$key] = $value;
        }

        return $names;
    }

    /**
     * Make a readable name from a permission code: "articles.edit" becomes "Articles Edit".
     */
    protected static function headline(string $code): string
    {
        return Str::headline(str_replace('.', ' ', $code));
    }
}
