<?php

namespace Jurager\Teams\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class Group implements ValidationRule
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
        if (! is_scalar($value) || ! $this->team->groups()->where('code', (string) $value)->exists()) {
            $fail('The :attribute must be a valid group.');
        }
    }
}
