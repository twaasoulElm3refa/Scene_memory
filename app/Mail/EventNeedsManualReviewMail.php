<?php

namespace App\Mail;

use App\Models\EventRequestCreate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventNeedsManualReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly int $requestId;

    public readonly int $eventId;

    public readonly string $eventTitle;

    public readonly ?string $ownerName;

    public readonly ?string $ownerEmail;

    public readonly string $confidence;

    public readonly string $reason;

    public readonly string $reviewUrl;

    public function __construct(EventRequestCreate $request)
    {
        $request->loadMissing('events.user');

        $this->requestId = (int) $request->id;
        $this->eventId = (int) $request->event_id;
        $this->eventTitle = (string) ($request->events?->title ?: 'Untitled event');
        $this->ownerName = $request->events?->user?->name;
        $this->ownerEmail = $request->events?->user?->email;
        $this->confidence = number_format((float) $request->ai_confidence, 4, '.', '');
        $this->reason = (string) $request->ai_reason;
        $this->reviewUrl = url('/admin/requests/'.$request->id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Event request #{$this->requestId} needs manual review",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-needs-manual-review');
    }

    public function attachments(): array
    {
        return [];
    }
}
