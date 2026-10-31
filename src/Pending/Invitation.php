<?php

namespace Jurager\Teams\Pending;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Jurager\Teams\Events\TeamMemberInvited;
use Jurager\Teams\Events\TeamMemberInviting;
use Jurager\Teams\Mail\Invitation as InvitationMail;
use RuntimeException;

class Invitation
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

            $rateLimit = Config::get('teams.invitations.rate_limit', []);

            if ($rateLimit['enabled'] ?? false) {
                $this->throttle($rateLimit);
            }

            TeamMemberInviting::dispatch($this->team, $this->email, $role);

            $invitation = $this->team->invitations()->firstOrNew(['email' => $this->email]);
            $invitation->fill(['role_id' => $role->getKey()])->setUpdatedAt($invitation->freshTimestamp());

            if (! $invitation->save()) {
                throw new RuntimeException('The invitation could not be saved.');
            }

            TeamMemberInvited::dispatch($this->team, $this->email, $role);

            $this->team->getConnection()->afterCommit(fn () => Mail::to($this->email)->send(new InvitationMail($invitation)));

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

    protected function throttle(array $config): void
    {
        $connection = $this->team->getConnection();

        $key = 'teams:invitations:'.hash('sha256', serialize([
            $connection->getName(), $connection->getDatabaseName(), $this->team->getTable(), (string) $this->team->getKey(),
        ]));

        $email = hash('sha256', mb_strtolower(trim($this->email)));

        $limits = [
            $key => ($config['team'] ?? []) + ['max_attempts' => 10, 'decay_seconds' => 3600],
            "{$key}:{$email}" => ($config['email'] ?? []) + ['max_attempts' => 1, 'decay_seconds' => 60],
        ];

        $delays = [];

        foreach ($limits as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit['max_attempts'])) {
                $delays[] = RateLimiter::availableIn($key);
            }
        }

        if ($delays !== []) {
            throw new ThrottleRequestsException(__('Too many team invitations. Please try again later.'), null, [
                'Retry-After' => max($delays),
            ]);
        }

        foreach ($limits as $key => $limit) {
            RateLimiter::hit($key, $limit['decay_seconds']);
        }
    }
}
