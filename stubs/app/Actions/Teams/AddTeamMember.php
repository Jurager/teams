<?php

namespace App\Actions\Teams;

use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Jurager\Teams\Contracts\AddsTeamMembers;
use Jurager\Teams\Events\AddingTeamMember;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Rules\Role;
use Jurager\Teams\Teams;

class AddTeamMember implements AddsTeamMembers
{
    /**
     * Add a new team member to the specified team.
     *
     * @param  object  $user  The user initiating the action
     * @param  object  $team  The team to which the member is being added
     * @param  string $email  Email of the member to be added
     * @param  string|null $role  Role of the member within the team
     * @return void
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws ValidationException
     */
    public function add(object $user, object $team, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($team, $email, $role);

        $member = Teams::findUserByEmailOrFail($email);

        AddingTeamMember::dispatch($team, $member);

        $team->users()->attach($member, ['role' => $role]);

        TeamMemberAdded::dispatch($team, $member);
    }


    /**
     * Validate the add member operation.
     *
     * @param  object  $team  The team to which the member is being added
     * @param  string $email  Email of the member to be added
     * @param  string|null $role  Role of the member within the team
     * @return void
     *
     * @throws ValidationException
     */
    protected function validate(object $team, string $email, ?string $role): void
    {
        Validator::make(
            compact('email', 'role'),
            $this->rules($team),
            [
                'email.exists' => __('We were unable to find a registered user with this email address.'),
            ]
        )->after(
            $this->ensureUserIsNotAlreadyOnTeam($team, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for adding a team member.
     *
     * @param  object  $team
     * @return array
     */
    protected function rules(object $team): array
    {
        return array_filter([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => $team->hasRoles() ? ['required', 'string', new Role($team)] : null,
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
