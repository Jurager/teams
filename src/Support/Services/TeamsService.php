<?php

namespace Jurager\Teams\Support\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class TeamsService
{
    /**
     * Permission decisions cached for the current request or job.
     *
     * The service is a scoped binding, so Laravel discards it between
     * HTTP requests, Octane requests and queued jobs.
     *
     * @var array<string, bool>
     */
    protected array $decisions = [];

    /**
     * Resolve the permission decision once per request or job.
     *
     * @param  Closure(): mixed  $resolve
     */
    public function rememberPermission(string $key, Closure $resolve): bool
    {
        return $this->decisions[$key] ??= (bool) $resolve();
    }

    /**
     * Flush all cached permission decisions.
     */
    public function forgetCachedPermissions(): void
    {
        $this->decisions = [];
    }

    /**
     * Create a new instance of the configured model.
     */
    public function instance(string $model): Model
    {
        $class = $this->model($model);

        return new $class;
    }

    /**
     * Get the configured model class.
     *
     * @return class-string<Model>
     */
    public function model(string $model): string
    {
        $class = Config::get("teams.models.{$model}");

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new RuntimeException(sprintf(
                'Config key [teams.models.%s] must be an Eloquent model class, %s given.',
                $model,
                is_string($class) ? "[{$class}]" : get_debug_type($class),
            ));
        }

        if ($model === 'membership' && ! is_subclass_of($class, Pivot::class)) {
            throw new RuntimeException('Config key [teams.models.membership] must extend Eloquent Pivot.');
        }

        return $class;
    }
}
