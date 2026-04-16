<?php

namespace Jurager\Teams\Support\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use RuntimeException;

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
        $this->models = Config::get('teams.models');
    }

    /**
     * Returns object instance based on name.
     *
     * @throws Exception
     */
    public function instance(string $model): object
    {
        return $this->getModel($model, true);
    }

    /**
     * Returns the model class based on name.
     *
     * @throws Exception
     */
    public function model(string $model): string
    {
        return $this->getModel($model);
    }

    /**
     * Gets a model instance by name.
     *
     * @throws Exception
     *
     * @template T of Model
     */
    private function getModel(string $key, bool $instance = false): string|object
    {
        $modelClass = $this->models[$key] ?? null;

        if (! $modelClass || ! class_exists($modelClass)) {
            throw new RuntimeException("Model class for key $key not found.");
        }

        return $instance ? new $modelClass() : $modelClass;
    }
}
