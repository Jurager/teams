<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Facades\Teams;
use Jurager\Teams\Record;

/** A permission allowed or forbidden to one recipient on one team record. */
class Ability extends Model
{
    protected $fillable = ['permission_id', 'entity_id', 'entity_type', 'recipient_id', 'recipient_type', 'forbidden', 'expires_at'];

    protected $casts = ['forbidden' => 'boolean', 'expires_at' => 'immutable_datetime'];

    public function __construct(array $attributes = [])
    {
        $this->mergeFillable([Config::get('teams.foreign_keys.team_id')]);

        parent::__construct($attributes);
    }

    public function getTable(): string
    {
        return $this->table ?? Config::get('teams.tables.abilities', 'abilities');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Teams::model('permission'), 'permission_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForRecord(Builder $query, Model|string|Record $record, int|string|null $key = null): void
    {
        $record = $record instanceof Record ? $record : Record::from($record, $key);

        $query->where($query->qualifyColumn('entity_type'), $record->type)
            ->where($query->qualifyColumn('entity_id'), $record->id);
    }

    /** @param iterable<Model> $records */
    public function scopeForRecords(Builder $query, iterable $records): void
    {
        $this->whereModels($query, 'entity', $records);
    }

    /** @param iterable<Model> $recipients */
    public function scopeForRecipients(Builder $query, iterable $recipients): void
    {
        $this->whereModels($query, 'recipient', $recipients);
    }

    /** @param iterable<Model> $models */
    protected function whereModels(Builder $query, string $relation, iterable $models): void
    {
        $types = collect($models)->groupBy(fn (Model $model): string => $model->getMorphClass());

        $query->where(function (Builder $query) use ($relation, $types) {
            if ($types->isEmpty()) {
                $query->whereRaw('1 = 0');
            }

            foreach ($types as $type => $models) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where($query->qualifyColumn("{$relation}_type"), $type)
                    ->whereIn($query->qualifyColumn("{$relation}_id"), $models->map->getKey()->all()));
            }
        });
    }
}
