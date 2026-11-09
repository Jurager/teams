<?php

namespace Jurager\Teams;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Facades\Teams;

/** The permissions a request may use, optionally within one team or on one record. */
class Scope
{
    protected int|string|null $team = null;

    protected ?Model $record = null;

    /**
     * @param  list<string>  $permissions
     */
    protected function __construct(protected array $permissions) {}

    /**
     * Start a scope that permits the given permissions. Without any, it permits nothing.
     */
    public static function to(string|BackedEnum|array ...$permissions): static
    {
        return new static(array_map(
            fn ($permission) => $permission instanceof BackedEnum ? (string) $permission->value : $permission,
            Arr::flatten($permissions),
        ));
    }

    /**
     * Limit the scope to one team.
     */
    public function in(Model|int|string $team): static
    {
        $this->team = $team instanceof Model ? $team->getKey() : $team;

        return $this;
    }

    /**
     * Limit the scope to one record.
     */
    public function on(Model $record): static
    {
        $this->record = $record;

        return $this;
    }

    /**
     * Determine if the scope permits the ability on the subject of a gate check.
     *
     * A scope limited to a team or a record permits only checks on a model of that team or that record.
     */
    public function permits(string|BackedEnum $ability, mixed $subject = null): bool
    {
        if (array_intersect(Teams::model('permission')::codes($ability), $this->permissions) === []) {
            return false;
        }

        if ($this->record !== null && ! ($subject instanceof Model && $this->record->is($subject))) {
            return false;
        }

        return $this->team === null || ($subject instanceof Model && $this->teamKey($subject) === (string) $this->team);
    }

    /**
     * Get the key of the team itself, or of the team of a record.
     */
    protected function teamKey(Model $subject): ?string
    {
        $team = Teams::model('team');
        $key = $subject instanceof $team
            ? $subject->getKey()
            : $subject->getAttribute(Config::get('teams.foreign_keys.team_id'));

        return is_int($key) || is_string($key) ? (string) $key : null;
    }
}
