<?php

namespace Jurager\Teams\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class Member implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * The owner isn't a member, so they pass only when `$owner` is true.
     */
    public function __construct(protected Model $team, protected bool $owner = false)
    {
        //
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! ($this->owns((string) $value) || $this->team->members()->whereKey($value)->exists())) {
            $fail('The selected :attribute is not a member of the team.');
        }
    }

    /**
     * Determine if the owner is allowed and the value is their key.
     */
    protected function owns(string $value): bool
    {
        return $this->owner && (string) $this->team->getAttribute('user_id') === $value;
    }
}
