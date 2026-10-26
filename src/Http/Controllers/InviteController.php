<?php

namespace Jurager\Teams\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

class InviteController extends Controller
{
    /**
     * Accept the invitation on behalf of the invited user.
     */
    public function inviteAccept(Request $request, int|string $invitationId): RedirectResponse
    {
        $invitation = TeamsFacade::model('invitation')::query()->findOrFail($invitationId);

        abort_unless($invitation->user?->is($request->user()), 403);

        $team = $invitation->team;
        $team->inviteAccept($invitation->getKey());

        return redirect('/')->with('status', __('Success! You have accepted the invitation to join the :team team.', ['team' => $team->name]));
    }
}
