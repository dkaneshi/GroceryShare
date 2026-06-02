<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShareNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public \App\Models\Sister $sister,
        public \App\Models\GroceryWeek $week,
        public float $amount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your grocery share for the week of {$this->week->week_date->format('F j, Y')}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.share-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
