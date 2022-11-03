<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Jurager\Teams\Models\Capability as CapabilityModel;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 *
 * @mixin Builder
 */
class Capability extends CapabilityModel
{

}
