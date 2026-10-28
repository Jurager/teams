<?php

namespace Jurager\Teams\Events;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AbilityUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * A null value means the rule is absent; false allows and true forbids.
     */
    public function __construct(
        public Model $ability,
        public Model $target,
        public ?bool $previous,
        public ?bool $forbidden,
        public ?CarbonInterface $previousExpiresAt = null,
        public ?CarbonInterface $expiresAt = null,
    ) {}
}
