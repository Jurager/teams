<?php

namespace App\Models;

use Jurager\Teams\Models\Membership as ModelMembership;

class Membership extends ModelMembership
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
}
