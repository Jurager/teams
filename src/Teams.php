<?php

namespace Jurager\Teams;

use Illuminate\Support\Facades\App;

class Teams
{
    /**
     * Array of models loaded from configuration.
     *
     * @var array<string, string>
     */
    protected array $models;

    public function __construct()
    {
        $this->models = config('teams.models');
    }

    /**
     * Gets a model instance by name.
     *
     * @param string $model
     * @return object
     * @throws InvalidArgumentException
     */
    public function getModel(string $model): object
    {
        if (array_key_exists($model, $this->models)) {
            return new $this->models[$model]();
        }

        throw new \InvalidArgumentException("Model [{$model}] is not defined in the configuration.");
    }
}