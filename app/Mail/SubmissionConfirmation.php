<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubmissionConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Submission $submission)
    {
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: strtr(Setting::get('confirmation_email_subject', 'ICS Award 2026: Your registration is confirmed'), $this->placeholders()),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $link = route('submissions.show', $this->submission);

        $body = strtr(Setting::get('confirmation_email_body', "Dear {{name}},\n\n{{submission_link}}"), [
            ...array_map(e(...), $this->placeholders()),
            '{{submission_link}}' => "[{$link}]({$link})",
        ]);

        return new Content(
            markdown: 'mail.submission-confirmation',
            with: ['body' => $body],
        );
    }

    /**
     * The values substituted into the admin-editable subject and body.
     *
     * @return array<string, string>
     */
    private function placeholders(): array
    {
        $deadline = Setting::endOfDay('paper_deadline');

        return [
            '{{name}}' => $this->submission->user->name,
            '{{category}}' => $this->submission->awardCategory->name,
            '{{initiative_title}}' => $this->submission->initiative_title,
            '{{submission_link}}' => route('submissions.show', $this->submission),
            '{{deadline}}' => $deadline === null ? 'the announced deadline' : $deadline->format('j F Y, H:i').' WIB',
            '{{contact_email}}' => Setting::get('contact_email', ''),
        ];
    }
}
