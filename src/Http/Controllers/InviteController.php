<?php

namespace Jurager\Teams\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Jurager\Teams\Facades\Teams;

class InviteController extends Controller
{
    /**
     * Accept the invitation on behalf of the invited user.
     */
    public function __invoke(Request $request, int|string $invitation): RedirectResponse
    {
        $invitation = Teams::model('invitation')::query()->findOrFail($invitation);

        abort_unless($invitation->user?->is($request->user()), 403);

        $invitation->accept();

        return redirect('/')->with('status', __('Success! You have accepted the invitation to join the :team team.', ['team' => $invitation->team->name]));
    }
}
