<?php

namespace Jurager\Teams\Observers;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;
use RuntimeException;

class MembershipObserver
{
    public function saving(Pivot $membership): void
    {
        $teamKey = Config::get('teams.foreign_keys.team_id');
        $team = $membership->belongsTo(TeamsFacade::model('team'), $teamKey)->firstOrFail();

        if ((string) $team->user_id === (string) $membership->user_id) {
            throw new RuntimeException('The team owner cannot be a member.');
        }

        $role = $membership->belongsTo(TeamsFacade::model('role'), 'role_id');

        if (! $role->where($role->getRelated()->qualifyColumn($teamKey), $team->getKey())->exists()) {
            throw new RuntimeException('The role does not belong to the membership team.');
        }
    }
}
