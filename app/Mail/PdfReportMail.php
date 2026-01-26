<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class PdfReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $userEmail,
        public string $userDisplayName,
        public string $testName,
        public string $emailContent,
        public array $pdfAttachments = [] // Array of ['path' => string, 'filename' => string] or ['data' => string, 'filename' => string]
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name')
            ),
            to: [new Address($this->userEmail, $this->userDisplayName)],
            subject: 'Your Strengths Compass Assessment Report - ' . $this->testName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->emailContent,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->pdfAttachments as $attachment) {
            if (isset($attachment['path']) && isset($attachment['filename']) && file_exists($attachment['path'])) {
                // Use file path if available (for queued emails)
                $attachments[] = Attachment::fromPath($attachment['path'])
                    ->as($attachment['filename'])
                    ->withMime('application/pdf');
            } elseif (isset($attachment['data']) && isset($attachment['filename'])) {
                // Fallback to data for immediate sending
                $attachments[] = Attachment::fromData(
                    fn () => $attachment['data'],
                    $attachment['filename']
                )->withMime('application/pdf');
            }
        }

        return $attachments;
    }
    
    /**
     * Clean up temporary files after email is sent
     * Called when the job completes
     */
    public function __destruct()
    {
        // Clean up temporary PDF files
        foreach ($this->pdfAttachments as $attachment) {
            if (isset($attachment['path']) && file_exists($attachment['path']) && isset($attachment['temporary']) && $attachment['temporary']) {
                @unlink($attachment['path']);
            }
        }
    }
}
