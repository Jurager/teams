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
     *
     * @var InvitationModel
     */
    public InvitationModel $invitation;

    /**
     * Create a new message instance.
     *
     * @param InvitationModel $invitation
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
        return $this->markdown('teams::mail.invitation', ['acceptUrl' => URL::signedRoute('invitations.accept', [
            'invitation' => $this->invitation,
        ])])->subject('Team Invitation');
    }
}
