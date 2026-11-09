<?php

namespace Jurager\Teams;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Models\Ability;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Invitation;
use Jurager\Teams\Models\Membership;
use Jurager\Teams\Models\Permission;
use Jurager\Teams\Models\Role;
use RuntimeException;

class Teams
{
    /**
     * The package classes that configured models must extend.
     *
     * @var array<string, class-string<Model>>
     */
    protected const BASES = [
        'membership' => Membership::class,
        'invitation' => Invitation::class,
        'role' => Role::class,
        'permission' => Permission::class,
        'ability' => Ability::class,
        'group' => Group::class,
    ];

    /**
     * The callback that returns the scope of a user's request.
     *
     * @var (Closure(Authenticatable): ?Scope)|null
     */
    protected ?Closure $scope = null;

    /**
     * Register the callback that returns the scope of a user's request, or null when it has none.
     *
     * @param  callable(Authenticatable): ?Scope  $callback
     */
    public function scope(callable $callback): void
    {
        $this->scope = $callback(...);
    }

    /**
     * Get the scope of the user's request, or null when it has none.
     */
    public function scoped(Authenticatable $user): ?Scope
    {
        return $this->scope === null ? null : ($this->scope)($user);
    }

    /**
     * Get the configured model class.
     *
     * @return class-string<Model>
     */
    public function model(string $model): string
    {
        $class = Config::get("teams.models.{$model}");
        $base = self::BASES[$model] ?? Model::class;

        if (! is_string($class) || ! is_a($class, $base, true)) {
            throw new RuntimeException(sprintf(
                'Config key [teams.models.%s] must be a class that extends [%s], %s given.',
                $model,
                $base,
                is_string($class) ? "[{$class}]" : get_debug_type($class),
            ));
        }

        return $class;
    }
}
