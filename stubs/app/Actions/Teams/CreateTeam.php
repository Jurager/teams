<?php

namespace App\Actions\Teams;

use Illuminate\Support\Facades\Validator;
use Jurager\Teams\Contracts\CreatesTeams;
use Jurager\Teams\Events\AddingTeam;

class CreateTeam implements CreatesTeams
{
    /**
     * Validate and create a new team for the given user.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(mixed $user, array $input): mixed
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
        ])->validateWithBag('createTeam');

        AddingTeam::dispatch($user);

        return $user->ownedTeams()->create([
            'name' => $input['name'],
        ]);
    }
}
