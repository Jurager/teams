<?php

namespace Jurager\Teams\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

class Invitation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['team_id', 'role_id', 'email'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->fillable[] = Config::get('teams.foreign_keys.team_id');
    }

    /**
     * Get the team that the invitation belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get the team role that the invitation belongs to.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('role'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get the user that the invitation belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(TeamsFacade::model('user'), 'email', 'email');
    }
}
