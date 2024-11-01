<?php

namespace Jurager\Teams\Support\Facades;

use Illuminate\Support\Facades\Facade;
class Teams extends Facade
{
    /**
     * Gets the facade name.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'teams';
    }

    /**
     * Returns object instance based on name.
     *
     * @param string $model
     * @return object
     */
    public static function instance(string $model): object
    {
        // Use a service object to obtain a model
        return app('teams')->getModel($model, true);
    }

    /**
     * Returns the model class based on name.
     *
     * @param string $model
     * @return string
     */
    public static function model(string $model): string
    {
        // Use a service object to obtain a model
        return app('teams')->getModel($model);
    }
}