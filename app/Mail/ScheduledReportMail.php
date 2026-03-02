<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduledReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $reportName,
        public readonly string $reportType,
        public readonly string $period,
        public readonly string $csvContent,
        public readonly string $fileName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Scheduled Report: '.$this->reportName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reports.scheduled',
        );
    }

    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $this->csvContent,
                $this->fileName
            )->withMime('text/csv'),
        ];
    }
}

