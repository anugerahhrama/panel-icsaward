<?php

namespace App\Mail;

use App\Enums\SubmissionStatus;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent from `SendVerificationDecision`, which already runs on the queue and guards against duplicates.
 */
class VerificationDecision extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Fallback templates per decision, used until the admin saves one under Settings → Email Templates.
     *
     * @var array<string, array{subject: string, body: string}>
     */
    private const array FALLBACKS = [
        'qualified' => [
            'subject' => 'ICS Award 2026: Your submission is qualified',
            'body' => "Dear {{name}},\n\nYour submission \"{{initiative_title}}\" passed the administrative check.\n\n{{dashboard_link}}",
        ],
        'needs_revision' => [
            'subject' => 'ICS Award 2026: Your submission needs revision',
            'body' => "Dear {{name}},\n\n{{revision_note}}\n\nPlease revise by {{revision_deadline}}.\n\n{{dashboard_link}}",
        ],
        'disqualified' => [
            'subject' => 'ICS Award 2026: Update on your submission',
            'body' => "Dear {{name}},\n\n{{disqualified_reason}}\n\n{{dashboard_link}}",
        ],
    ];

    /**
     * Create a new message instance.
     *
     * @param  array{subject: string, body: string, contact_email: string}|null  $template  Unsaved template from Settings → Email Templates, used by the preview instead of the stored one.
     */
    public function __construct(public Submission $submission, public ?array $template = null) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: strtr($this->templatePart('subject'), $this->placeholders()),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $link = route('dashboard');

        $body = strtr($this->templatePart('body'), [
            ...array_map(e(...), $this->placeholders()),
            '{{dashboard_link}}' => "[{$link}]({$link})",
        ]);

        return new Content(
            markdown: 'mail.verification-decision',
            with: ['body' => $body],
        );
    }

    /**
     * The settings key prefix of the template for this submission's decision.
     */
    private function templateKey(): string
    {
        return match ($this->submission->status) {
            SubmissionStatus::NeedsRevision => 'needs_revision',
            SubmissionStatus::Disqualified => 'disqualified',
            default => 'qualified',
        };
    }

    /**
     * The unsaved template part when previewing, otherwise the stored one with its fallback.
     */
    private function templatePart(string $part): string
    {
        return $this->template[$part] ?? Setting::get("{$this->templateKey()}_email_{$part}", $this->fallback($part));
    }

    private function fallback(string $part): string
    {
        return self::FALLBACKS[$this->templateKey()][$part];
    }

    /**
     * The values substituted into the admin-editable subject and body.
     *
     * @return array<string, string>
     */
    private function placeholders(): array
    {
        $deadline = $this->submission->revision_deadline?->setTimezone(Setting::EVENT_TIMEZONE);

        return [
            '{{name}}' => $this->submission->user->name,
            '{{category}}' => $this->submission->awardCategory->name,
            '{{initiative_title}}' => $this->submission->initiative_title,
            '{{dashboard_link}}' => route('dashboard'),
            '{{revision_note}}' => $this->submission->revision_note ?? '',
            '{{revision_deadline}}' => $deadline === null ? '' : $deadline->format('j F Y, H:i').' WIB',
            '{{disqualified_reason}}' => $this->submission->disqualified_reason ?? '',
            '{{contact_email}}' => $this->template['contact_email'] ?? Setting::get('contact_email', ''),
        ];
    }
}
