<?php

namespace Jurager\Teams\Support\Services;

class TeamsService
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
     * @param string $key
     * @param bool $instance
     * @return string|object
     * @throws \Exception
     */
    public function getModel(string $key, bool $instance = false): string|object
    {
        $modelClass = $this->models[$key] ?? null;

        if (!$modelClass || !class_exists($modelClass)) {
            throw new \RuntimeException("Model class for key {$key} not found.");
        }

        return $instance ? new $modelClass : $modelClass;
    }
}