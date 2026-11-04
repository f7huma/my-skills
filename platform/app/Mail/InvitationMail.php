<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationMail extends Mailable
{
    public function __construct(public string $name, public string $url, public $expiresAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'دعوة للدخول إلى منصة '.config('office.short_name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invitation');
    }
}
