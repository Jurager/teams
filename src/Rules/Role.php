<?php

namespace Jurager\Teams\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Role implements ValidationRule
{

    public function __construct(private $team) {
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!in_array($value, $this->team->roles->pluck('name')->all(), true)) {
            $fail('The :attribute must be a valid role.');
        }
    }
}
