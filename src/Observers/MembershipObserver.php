<?php

namespace Jurager\Teams\Observers;

use Illuminate\Support\Facades\Config;
use Jurager\Teams\Models\Membership;
use RuntimeException;

class MembershipObserver
{
    /**
     * Keep the owner out of the members and the role inside the membership's team.
     */
    public function saving(Membership $membership): void
    {
        $team = $membership->team()->firstOrFail();

        if ((string) $team->user_id === (string) $membership->user_id) {
            throw new RuntimeException('The team owner cannot be a member.');
        }

        $roles = $membership->role();

        if (! $roles->where($roles->getRelated()->qualifyColumn(Config::get('teams.foreign_keys.team_id')), $team->getKey())->exists()) {
            throw new RuntimeException('The role does not belong to the membership team.');
        }
    }
}
