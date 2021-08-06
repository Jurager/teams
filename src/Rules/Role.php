<?php

namespace Jurager\Teams\Rules;

use Illuminate\Contracts\Validation\Rule;
use Jurager\Teams\Teams;

class Role implements Rule
{
    
    public function __construct(private $team)
    {
        
    }
    
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return in_array($value, $this->team->roles->pluck('name')->toArray());
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
