<?php

namespace App\Actions\Teams;

use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Jurager\Teams\Contracts\InvitesTeamMembers;
use Jurager\Teams\Events\InvitingTeamMember;
use Jurager\Teams\Mail\Invitation;
use Jurager\Teams\Rules\Role;
use Jurager\Teams\Teams;

class InviteTeamMember implements InvitesTeamMembers
{
    /**
     * Invite a new team member to the specified team.
     *
     * @param  mixed  $user  The user initiating the invitation
     * @param  mixed  $team  The team to invite the new member to
     * @param  string $email  Email of the invited member
     * @param  string|null $role  Role assigned to the invited member
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function invite(mixed $user, mixed $team, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($team, $email, $role);

        InvitingTeamMember::dispatch($team, $email, $role);

        $invitation = $team->invitations()->create(compact('email', 'role'));

        Mail::to($email)->send(new Invitation($invitation));
    }

    /**
     * Validate the invite member request.
     *
     * @param  mixed  $team  The team to invite the new member to
     * @param  string $email  Email of the invited member
     * @param  string|null $role  Role assigned to the invited member
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validate(mixed $team, string $email, ?string $role)
    {
        Validator::make(
            compact('email', 'role'),
            $this->inviteValidationRules($team),
            ['email.unique' => __('This user has already been invited to the team.')]
        )->after(
            $this->userNotOnTeam($team, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Define validation rules for inviting a team member.
     *
     * @param  mixed  $team
     * @return array
     */
    protected function rules(mixed $team): array
    {
        return array_filter([
            'email' => [
                'required',
                'email',
                Rule::unique('invitations')->where(fn ($query) => $query->where(config('teams.foreign_keys.team_id', 'team_id'), $team->id)),
            ],
            'role' => Teams::hasRoles() ? ['required', 'string', new Role($team)] : null,
        ]);
    }

    /**
     * Ensure the user is not already a member of the team.
     *
     * @param  mixed  $team
     * @param  string $email
     * @return Closure
     */
    protected function ensureUserIsNotAlreadyOnTeam(mixed $team, string $email): Closure
    {
        return static function ($validator) use ($team, $email) {
            if ($team->hasUserWithEmail($email)) {
                $validator->errors()->add('email', __('This user already belongs to the team.'));
            }
        };
    }
}
