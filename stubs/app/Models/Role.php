<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Jurager\Teams\Teams;
use Jurager\Teams\Models\Role as RoleModel;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property int|null $level
 *
 * @mixin Builder
 */
class Role extends RoleModel
{
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [ 'team_id', 'name', 'description', 'level'];

}
