<?php

namespace Jurager\Teams\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class Invitable implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(protected Model $team)
    {
        //
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || $this->team->owner()->where('email', $value)->exists()
            || $this->team->members()->where('email', $value)->exists()) {
            $fail('The :attribute is already used by a member of the team.');
        }
    }
}
