<?php

namespace Jurager\Teams\Rules;

use Illuminate\Contracts\Validation\Rule;
use Jurager\Teams\Teams;

class Role implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return in_array($value, array_keys(Teams::$roles));
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('The :attribute must be a valid role.');
    }
}
