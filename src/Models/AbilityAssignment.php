<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class AbilityAssignment extends MorphPivot
{
    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
