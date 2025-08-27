<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;
use Exception;

class InviteController extends Controller
{
    /**
     * Accept the given invite.
     *
     * @param Request $request
     * @param $invitationId
     * @return Application|\Illuminate\Foundation\Application|RedirectResponse|Redirector
     * @throws Exception
     */
    public function inviteAccept(Request $request, $invitationId): \Illuminate\Foundation\Application|Redirector|Application|RedirectResponse
    {
        // Get the invitation model
        $invitation = TeamsFacade::instance('invitation')->whereKey($invitationId)->firstOrFail();

        // Get the team from invitation
        $team = $invitation->team;

        // Accept the invitation
        $team->inviteAccept($invitation->id);

        return redirect('/')->with('status', __('Success! You have accepted the invitation to join the :team team.', ['team' => $team->name]));
    }
}
