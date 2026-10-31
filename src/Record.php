<?php

namespace Jurager\Teams;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/** @internal Identity of a model or an external record in an explicit team context. */
class Record
{
    protected function __construct(
        public readonly string $type,
        public readonly int|string|null $id,
        public readonly ?Model $model = null,
    ) {}

    public static function from(Model|string $record, int|string|null $key = null): self
    {
        if (is_string($record)) {
            if ($key === null) {
                throw new InvalidArgumentException('A record type requires a key.');
            }

            $class = is_subclass_of($record, Model::class) ? $record : Relation::getMorphedModel($record);

            if ($class === null) {
                return self::external($record, $key);
            }

            if (! is_subclass_of($class, Model::class)) {
                throw new InvalidArgumentException('A model type must resolve to an Eloquent model.');
            }

            $record = new $class;
            $record->setAttribute($record->getKeyName(), $key);
        }

        return new self($record->getMorphClass(), $record->getKey(), $record);
    }

    public function belongsTo(Model $team): bool
    {
        if ($team->getKey() === null) {
            return false;
        }

        if ($this->model === null) {
            return true;
        }

        $foreignKey = Config::get('teams.foreign_keys.team_id');

        return isset($this->model->getAttributes()[$foreignKey])
            && (string) $this->model->getAttribute($foreignKey) === (string) $team->getKey();
    }

    public function key(): string
    {
        return "{$this->type}:{$this->id}";
    }

    protected static function external(string $type, int|string $key): self
    {
        if (class_exists($type) || interface_exists($type)) {
            throw new InvalidArgumentException('A model type must resolve to an Eloquent model.');
        }

        if ($type === '' || trim($type) !== $type || mb_strlen($type) > 255) {
            throw new InvalidArgumentException('An external record type must contain between 1 and 255 characters without surrounding whitespace.');
        }

        foreach (array_keys(Relation::morphMap()) as $alias) {
            if (strcasecmp($type, (string) $alias) === 0) {
                throw new InvalidArgumentException('An external record type must not differ from a model morph alias only in case.');
            }
        }

        $normalized = ltrim((string) $key, '0') ?: '0';
        $id = filter_var($normalized, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if (! ctype_digit((string) $key) || $id === false) {
            throw new InvalidArgumentException('An external record key must be a non-negative integer within the PHP integer range.');
        }

        return new self($type, $id);
    }
}
