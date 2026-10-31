<?php

namespace Jurager\Teams\Pending;

use BackedEnum;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use Jurager\Teams\Events\AbilityUpdated;
use Jurager\Teams\Record;
use LogicException;
use RuntimeException;

class Ability
{
    protected string $permission;

    protected ?CarbonInterface $expiresAt = null;

    /** Null removes the rule, false allows, and true forbids. */
    public function __construct(
        protected Model $team,
        protected Model $recipient,
        string|BackedEnum $permission,
        protected ?bool $forbidden,
    ) {
        $this->permission = $permission instanceof BackedEnum ? $permission->value : $permission;
    }

    public function until(DateTimeInterface $expiresAt): static
    {
        if ($this->forbidden === null) {
            throw new LogicException('Only allow and forbid rules may expire.');
        }

        $this->expiresAt = Date::instance($expiresAt)->setTimezone(Date::now()->getTimezone())->startOfSecond()->toImmutable();

        return $this;
    }

    public function on(Model|string $record, int|string|null $key = null): void
    {
        $record = Record::from($record, $key);

        if ($record->id === null) {
            throw new InvalidArgumentException('The record must be saved, or given as a model class and key.');
        }

        if ($this->recipient->getKey() === null) {
            throw new InvalidArgumentException('The recipient must be saved.');
        }

        $this->team->getConnection()->transaction(function () use ($record) {
            $this->team->lockForUpdate()->findOrFail($this->team->getKey());

            $ability = $this->team->abilities()
                ->forRecord($record)
                ->whereMorphedTo('recipient', $this->recipient)
                ->whereRelation('permission', 'code', $this->permission)
                ->lockForUpdate()->first();

            $previous = $ability?->forbidden;
            $previousExpiresAt = $ability?->expires_at;

            if ($previous === $this->forbidden && $previousExpiresAt?->toDateTimeString() === $this->expiresAt?->toDateTimeString()) {
                return;
            }

            if ($this->forbidden === null) {
                if ($ability === null) {
                    return;
                }

                if ($ability->forceDelete() === false || $ability->fresh() !== null) {
                    throw new RuntimeException('The rule could not be removed.');
                }
            } else {
                $ability = $this->save($ability, $record);
            }

            AbilityUpdated::dispatch(
                $ability->getKey(),
                $this->team->getKey(),
                $ability->permission_id,
                $this->permission,
                $record->type,
                $record->id,
                $this->recipient->getMorphClass(),
                $this->recipient->getKey(),
                $previous,
                $this->forbidden,
                $previousExpiresAt,
                $this->expiresAt,
            );
        });

        $this->recipient->unsetRelation('abilities');
    }

    protected function save(?Model $ability, Record $record): Model
    {
        if ($ability === null) {
            $permission = $this->team->permissions()->firstOrCreate(['code' => $this->permission]);

            if (! $permission->exists) {
                throw new RuntimeException('The permission could not be saved.');
            }

            $ability = $this->team->abilities()->make([
                'entity_type' => $record->type,
                'entity_id' => $record->id,
                'recipient_type' => $this->recipient->getMorphClass(),
                'recipient_id' => $this->recipient->getKey(),
                'permission_id' => $permission->getKey(),
            ]);
        }

        $identity = $ability->only([
            Config::get('teams.foreign_keys.team_id'), 'permission_id',
            'entity_type', 'entity_id', 'recipient_type', 'recipient_id',
        ]);

        $ability->fill(['forbidden' => $this->forbidden, 'expires_at' => $this->expiresAt]);

        if (! $ability->save()) {
            throw new RuntimeException('The rule could not be saved.');
        }

        $saved = $ability->fresh();

        if ($saved === null || $saved->only(array_keys($identity)) != $identity || $saved->forbidden !== $this->forbidden
            || $saved->expires_at?->toDateTimeString() !== $this->expiresAt?->toDateTimeString()) {
            throw new RuntimeException('The rule could not be saved.');
        }

        return $saved;
    }
}
