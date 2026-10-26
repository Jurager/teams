<?php

namespace Jurager\Teams\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeamMemberInviting
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The team instance.
     */
    public Model $team;

    /**
     * The email address of the invitee.
     */
    public string $email;

    /**
     * The role of the invitee.
     */
    public Model $role;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Model $team, string $email, Model $role)
    {
        $this->team = $team;
        $this->email = $email;
        $this->role = $role;
    }
}
