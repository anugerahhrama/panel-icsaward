<?php

namespace App\Mail;

use App\Enums\Announcement;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent from `SendAnnouncement`, which already runs on the queue and guards against duplicates.
 */
class CategoryAnnouncement extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Fallback templates per announcement, used until the admin saves one under Settings → Email Templates.
     *
     * @var array<string, array{subject: string, body: string}>
     */
    public const array FALLBACKS = [
        'finalist_announcement' => [
            'subject' => 'ICS Award 2026: You are a Top 5 finalist',
            'body' => "Dear {{name}},\n\nCongratulations! Your initiative \"{{initiative_title}}\" is one of the Top 5 finalists in {{category}}.\n\nYou will pitch your initiative to the Board of Judges.\n\n**Pitching schedule:** {{pitching_schedule}}\n\n{{dashboard_link}}\n\nIf you have any questions, please contact us at {{contact_email}}.",
        ],
        'awarding_invitation' => [
            'subject' => 'ICS Award 2026: Invitation to the Awarding Night',
            'body' => "Dear {{name}},\n\nAs a finalist in {{category}} with \"{{initiative_title}}\", you are invited to the ICS Award 2026 Awarding Night, where the winners will be announced.\n\n**When & where:** {{awarding_night}}\n\n{{dashboard_link}}\n\nIf you have any questions, please contact us at {{contact_email}}.",
        ],
        'winner_announcement' => [
            'subject' => 'ICS Award 2026: Congratulations on your {{award}} award',
            'body' => "Dear {{name}},\n\nCongratulations! \"{{initiative_title}}\" received the **{{award}}** award in {{category}} at the ICS Award 2026.\n\n{{dashboard_link}}\n\nIf you have any questions, please contact us at {{contact_email}}.",
        ],
    ];

    /**
     * Create a new message instance.
     *
     * @param  array{subject: string, body: string, contact_email: string}|null  $template  Unsaved template from Settings → Email Templates, used by the preview instead of the stored one.
     */
    public function __construct(public Submission $submission, public Announcement $announcement, public ?array $template = null) {}

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
            markdown: 'mail.announcement',
            with: ['body' => $body],
        );
    }

    /**
     * The unsaved template part when previewing, otherwise the stored one with its fallback.
     */
    private function templatePart(string $part): string
    {
        return $this->template[$part] ?? Setting::get("{$this->announcement->templateKey()}_email_{$part}", $this->fallback($part));
    }

    private function fallback(string $part): string
    {
        return self::FALLBACKS[$this->announcement->templateKey()][$part];
    }

    /**
     * The values substituted into the admin-editable subject and body.
     *
     * @return array<string, string>
     */
    private function placeholders(): array
    {
        return [
            '{{name}}' => $this->submission->user->name,
            '{{category}}' => $this->submission->awardCategory->name,
            '{{initiative_title}}' => $this->submission->initiative_title,
            '{{dashboard_link}}' => route('dashboard'),
            '{{pitching_schedule}}' => $this->pitchingSchedule(),
            '{{awarding_night}}' => Setting::get('timeline_awarding_night') ?: 'to be announced',
            '{{award}}' => $this->submission->award?->label() ?? '',
            '{{contact_email}}' => $this->template['contact_email'] ?? Setting::get('contact_email', ''),
        ];
    }

    /**
     * The finalist's pitching time and place in WIB, or a note that it follows on the dashboard.
     */
    private function pitchingSchedule(): string
    {
        $session = $this->submission->awardCategory->pitchingSession;

        if ($session === null) {
            return 'to be announced on your dashboard';
        }

        $startsAt = ($this->submission->pitchingSlot === null ? $session->scheduled_at : $this->submission->pitchingSlot->starts_at)
            ->setTimezone(Setting::EVENT_TIMEZONE);

        $time = $this->submission->pitchingSlot === null
            ? $startsAt->format('j F Y').' (time to be announced)'
            : $startsAt->format('j F Y, H:i').' WIB';

        return implode(', ', array_filter([$time, $session->location, $session->meeting_link]));
    }
}
