<?php

namespace Jurager\Teams\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Jurager\Teams\Teams as Registry;

/**
 * @method static class-string<Model> model(string $model)
 *
 * @see Registry
 */
class Teams extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return Registry::class;
    }
}
