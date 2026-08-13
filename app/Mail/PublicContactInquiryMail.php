<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PublicContactInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{name: string, phone: string, email: string, message: string, submitted_at: string} $inquiry */
    public function __construct(
        public readonly array $inquiry,
        public readonly string $platformName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New '.$this->platformName.' Contact Inquiry - '.$this->inquiry['name'],
            // Keep the configured application sender as From. Reply-To makes
            // inbox replies go safely to the visitor without sender spoofing.
            replyTo: [new Address($this->inquiry['email'], $this->inquiry['name'])],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.public-contact-inquiry');
    }
}
