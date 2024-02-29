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
     * Invite a new team member to the given team.
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
     * Validate the invite member operation.
     *
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validate(mixed $team, string $email, ?string $role)
    {
        Validator::make(compact('email', 'role'), $this->rules($team), [
            'email.unique' => __('This user has already been invited to the team.'),
        ])->after(
            $this->ensureUserIsNotAlreadyOnTeam($team, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for inviting a team member.
     */
    protected function rules(mixed $team): array
    {
        return array_filter([
            'email' => ['required', 'email', Rule::unique('invitations')->where(function ($query) use ($team) {
                $query->where(config('teams.foreign_keys.team_id', 'team_id'), $team->id);
            })],
            'role' => Teams::hasRoles()
                ? ['required', 'string', new Role($team)]
                : null,
        ]);
    }

    /**
     * Ensure that the user is not already on the team.
     *
     * @param  mixed  $team
     */
    protected function ensureUserIsNotAlreadyOnTeam($team, string $email): Closure
    {
        return static function ($validator) use ($team, $email) {
            $validator->errors()->addIf(
                $team->hasUserWithEmail($email),
                'email',
                __('This user already belongs to the team.')
            );
        };
    }
}
