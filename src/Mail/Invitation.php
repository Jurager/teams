<?php

namespace Jurager\Teams\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class Invitation extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Model $invitation)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Team Invitation'));
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(markdown: 'teams::emails.invitation', with: ['url' => $this->acceptUrl()]);
    }

    /**
     * Get the signed URL for accepting the invitation.
     */
    protected function acceptUrl(): string
    {
        $parameters = ['invitation_id' => $this->invitation->getKey()];

        $expire = Config::get('teams.invitations.expire');

        return $expire
            ? URL::temporarySignedRoute('teams.invitations.accept', now()->addMinutes((int) $expire), $parameters)
            : URL::signedRoute('teams.invitations.accept', $parameters);
    }
}
