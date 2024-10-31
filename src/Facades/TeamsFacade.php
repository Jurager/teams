<?php

namespace Jurager\Teams\Facades;

use Jurager\Teams\Teams;
use Illuminate\Support\Facades\Facade;

class TeamsFacade extends Facade
{
    /**
     * Gets the facade name.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return Teams::class;
    }

    /**
     * Forwards static calls to the teams instance.
     *
     * @param string $method
     * @param array $args
     * @return object
     */
    public static function __callStatic($method, $args): object
    {
        return app('teams')->getModel($method);
    }
}