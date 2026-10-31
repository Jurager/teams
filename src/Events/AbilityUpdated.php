<?php

namespace Jurager\Teams\Events;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class AbilityUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly ?CarbonInterface $previousExpiresAt;

    public readonly ?CarbonInterface $expiresAt;

    /** A null state means absent, false allows, and true forbids. Values describe the change at dispatch time. */
    public function __construct(
        public readonly int|string $abilityId,
        public readonly int|string $teamId,
        public readonly int|string $permissionId,
        public readonly string $permission,
        public readonly string $entityType,
        public readonly int|string $entityId,
        public readonly string $recipientType,
        public readonly int|string $recipientId,
        public readonly ?bool $previous,
        public readonly ?bool $forbidden,
        ?CarbonInterface $previousExpiresAt = null,
        ?CarbonInterface $expiresAt = null,
    ) {
        $this->previousExpiresAt = $previousExpiresAt?->toImmutable();
        $this->expiresAt = $expiresAt?->toImmutable();
    }
}
