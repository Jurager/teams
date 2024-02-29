<?php

namespace Jurager\Teams\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Jurager\Teams\Models\Invitation as InvitationModel;

class Invitation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The team invitation instance.
     */
    public InvitationModel $invitation;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(InvitationModel $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this->markdown('teams::emails.invitation', ['acceptUrl' => URL::signedRoute('invitations.accept', [
            'invitation' => $this->invitation,
        ])])->subject(__('Team Invitation'));
    }
}
