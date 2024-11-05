<?php

namespace Jurager\Teams\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Jurager\Teams\Support\Services\TeamsService;

/**
 * @method static string model(string $model)
 * @method static object instance(string $model)
 *
 * @see \Jurager\Teams\Support\Services\TeamsService
 */
class Teams extends Facade
{
    /**
     * Gets the facade name.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return TeamsService::class;
    }
}