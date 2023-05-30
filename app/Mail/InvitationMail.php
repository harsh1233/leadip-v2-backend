<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $token;
    public $invited_user;
    public $company_name;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user,$token,$invited_user,$company_name)
    {
        $this->user         = $user;
        $this->token        = $token;
        $this->invited_user = $invited_user;
        $this->company_name = $company_name;
    
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        //Invitation to join %company name%'s network
        return new Envelope(
            subject: 'Invitation to join '.$this->company_name.' network', 
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            markdown: 'emails.InvitationMail',
            with: [
                'authUser'     => $this->user,
                'token'        => $this->token,
                'invitedUser'  => $this->invited_user,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
