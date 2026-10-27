<?php

namespace Jurager\Teams\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Jurager\Teams\Events\TeamMemberInvited;
use Jurager\Teams\Events\TeamMemberInviting;
use Jurager\Teams\Mail\Invitation;
use RuntimeException;

class PendingInvitation
{
    public function __construct(
        protected Model $team,
        protected string $email,
    ) {}

    /**
     * Invite the email to the team with the given role and send the invitation once it is saved.
     *
     * Inviting the same email again updates the pending invitation.
     */
    public function as(string|BackedEnum|Model $role): Model
    {
        return $this->team->getConnection()->transaction(function () use ($role) {

            $this->team->lockForUpdate()->findOrFail($this->team->getKey());

            if ($this->joined()) {
                throw new RuntimeException('This user already belongs to the team.');
            }

            $role = $role instanceof Model
                ? $this->team->roles()->whereKey($role)->firstOrFail()
                : $this->team->role($role);

            TeamMemberInviting::dispatch($this->team, $this->email, $role);

            $invitation = $this->team->invitations()->firstOrNew(['email' => $this->email]);
            $invitation->fill(['role_id' => $role->getKey()])->setUpdatedAt($invitation->freshTimestamp());

            if (! $invitation->save()) {
                throw new RuntimeException('The invitation could not be saved.');
            }

            TeamMemberInvited::dispatch($this->team, $this->email, $role);

            $this->team->getConnection()->afterCommit(fn () => Mail::to($this->email)->send(new Invitation($invitation)));

            return $invitation;
        });
    }

    /**
     * Determine if the owner or a member already uses the email.
     */
    protected function joined(): bool
    {
        return $this->team->owner()->where('email', $this->email)->exists()
            || $this->team->members()->where('email', $this->email)->exists();
    }
}
