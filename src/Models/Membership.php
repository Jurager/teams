<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

class Membership extends Pivot
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->table ?? Config::get('teams.tables.team_user', 'team_user');
    }

    /**
     * Get the role that the membership belongs to.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('role'), 'role_id');
    }

    /**
     * Get the team that the membership belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get the user that the membership belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('user'), 'user_id');
    }
}
