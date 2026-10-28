<?php

namespace Jurager\Teams\Support;

use BackedEnum;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use Jurager\Teams\Events\AbilityUpdated;
use Jurager\Teams\Support\Facades\Teams;
use LogicException;
use RuntimeException;

class PendingRule
{
    protected string $permission;

    protected ?CarbonInterface $expiresAt = null;

    /**
     * @param  Model  $target  The user, role or group the rule belongs to.
     * @param  bool|null  $forbidden  True to forbid, false to allow, null to remove the rule.
     */
    public function __construct(
        protected Model $team,
        protected Model $target,
        string|BackedEnum $permission,
        protected ?bool $forbidden,
    ) {
        $this->permission = $permission instanceof BackedEnum ? $permission->value : $permission;
    }

    /**
     * Apply the allow or forbid rule until the given time.
     */
    public function until(DateTimeInterface $expiresAt): static
    {
        if ($this->forbidden === null) {
            throw new LogicException('Only allow and forbid rules may expire.');
        }

        $this->expiresAt = Date::instance($expiresAt)->setTimezone(Date::now()->getTimezone())->startOfSecond()->toImmutable();

        return $this;
    }

    /**
     * Apply the rule to a record, given as a model or as a model class and key.
     */
    public function on(Model|string $record, int|string|null $key = null): void
    {
        $record = $this->record($record, $key);

        $this->target->unsetRelation('abilities');

        $this->forbidden === null ? $this->detach($record) : $this->attach($record);
    }

    /**
     * Create the team's ability for the record if needed and assign it to the target.
     */
    protected function attach(Model $record): void
    {
        $this->team->getConnection()->transaction(function () use ($record) {

            $this->team->lockForUpdate()->findOrFail($this->team->getKey());

            $ability = $this->team->abilities()->firstOrCreate([
                'entity_type' => $record->getMorphClass(),
                'entity_id' => $record->getKey(),
                'permission_id' => Teams::model('permission')::ids($this->team->getKey(), [$this->permission])[0],
            ]);

            $ability->lockForUpdate()->findOrFail($ability->getKey());

            $previous = $this->target->abilities()->whereKey($ability->getKey())->first();
            $forbidden = $previous === null ? null : (bool) $previous->pivot->forbidden;

            $previousExpiresAt = $previous?->pivot->expires_at;

            if ($forbidden === $this->forbidden && $previousExpiresAt?->toDateTimeString() === $this->expiresAt?->toDateTimeString()) {
                return;
            }

            $this->target->abilities()->syncWithoutDetaching([
                $ability->getKey() => ['forbidden' => $this->forbidden, 'expires_at' => $this->expiresAt],
            ]);

            $assignment = $this->target->abilities()->whereKey($ability->getKey())->first()?->pivot;

            if ($assignment === null || (bool) $assignment->forbidden !== $this->forbidden
                || $assignment->expires_at?->toDateTimeString() !== $this->expiresAt?->toDateTimeString()) {
                throw new RuntimeException('The rule could not be saved.');
            }

            AbilityUpdated::dispatch(
                $ability,
                $this->target,
                $forbidden,
                $this->forbidden,
                $previousExpiresAt,
                $this->expiresAt,
            );
        });
    }

    /**
     * Remove the target's rule for the record without creating anything.
     */
    protected function detach(Model $record): void
    {
        $this->team->getConnection()->transaction(function () use ($record) {
            $this->team->lockForUpdate()->findOrFail($this->team->getKey());

            $ability = $this->team->abilities()
                ->where('entity_type', $record->getMorphClass())
                ->where('entity_id', $record->getKey())
                ->whereRelation('permission', 'code', $this->permission)
                ->lockForUpdate()->first();

            if ($ability === null) {
                return;
            }

            $previous = $this->target->abilities()->whereKey($ability->getKey())->first();

            if ($previous !== null) {
                $this->target->abilities()->detach($ability->getKey());

                if ($this->target->abilities()->whereKey($ability->getKey())->exists()) {
                    throw new RuntimeException('The rule could not be removed.');
                }

                AbilityUpdated::dispatch(
                    $ability,
                    $this->target,
                    (bool) $previous->pivot->forbidden,
                    null,
                    $previous->pivot->expires_at,
                );
            }
        });
    }

    /**
     * Resolve a model, or a model class and key, to a model carrying the key.
     */
    protected function record(Model|string $record, int|string|null $key): Model
    {
        if (is_string($record)) {
            if (! is_subclass_of($record, Model::class)) {
                throw new InvalidArgumentException('The record must be an Eloquent model or model class.');
            }

            $model = new $record;
            $record = $model->setAttribute($model->getKeyName(), $key);
        }

        if ($record->getKey() === null) {
            throw new InvalidArgumentException('The record must be saved, or given as a model class and key.');
        }

        return $record;
    }
}
