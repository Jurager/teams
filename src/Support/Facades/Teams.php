<?php

namespace Jurager\Teams\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Jurager\Teams\Support\Services\TeamsService;

/**
 * @method static class-string<\Illuminate\Database\Eloquent\Model> model(string $model)
 *
 * @see TeamsService
 */
class Teams extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return TeamsService::class;
    }
}
