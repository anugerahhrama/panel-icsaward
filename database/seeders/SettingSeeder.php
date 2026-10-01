<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Seed default settings.
     *
     * Uses `firstOrCreate` (not `updateOrCreate`) so re-seeding never overwrites a value an admin has since edited.
     *
     * Email templates and the deck schedule are official values. `terms_organization` / `terms_individual` are
     * `[Placeholder]` text and `contact_email` still needs the team's confirmation: set both through
     * Admin → Settings before go-live (see the deploy checklist in `.ai/PROJECT.md`).
     */
    public function run(): void
    {
        $defaults = [
            'max_registrations_per_user' => '1',
            'is_registration_open' => '1',
            'paper_allowed_extensions' => 'pdf,pptx',
            'paper_max_size_mb' => '20',
            'require_statement_letter' => '1',
            'contact_email' => 'info@icsaward.id',
            'judging_stage' => 'closed',
            'normalization_enabled' => '1',
            'normalization_min_sample' => '3',
            'stage_1_weight' => '50',
            'stage_2_weight' => '50',
            ...DeckScheduleSeeder::SCHEDULE,
            'confirmation_email_subject' => 'ICS Award 2026: Your registration is confirmed. Next step: submit your paper',
            'confirmation_email_body' => <<<'MARKDOWN'
                Dear {{name}},

                Thank you for registering for the Indonesia Corporate Sustainability Award (ICS Award) 2026. We have received your registration:

                - **Category:** {{category}}
                - **Initiative:** {{initiative_title}}

                **NEXT STEP: SUBMIT YOUR PAPER**

                Please upload your Submission Paper and Statement Letter using your personal submission link:

                {{submission_link}}

                Before you submit, please make sure that you:

                1. Use the official Submission Paper Template and Statement Letter. Both can be downloaded from the submission page.
                2. Upload your completed files before the deadline on {{deadline}}. Submissions received after the deadline cannot be considered.

                Please note that each email address can submit one Submission Form, and each link is valid for one submission in one category.

                Once you have submitted, our committee will review the completeness of your documents. You can follow your status (Under Review, Qualified, or Needs Revision) on your dashboard.

                If you have any questions, please contact us at {{contact_email}}.

                Warm regards,<br>
                ICS Award 2026 Committee<br>
                Olahkarsa Group, with IBCSD as Knowledge Partner
                MARKDOWN,
            'qualified_email_subject' => 'ICS Award 2026: Your submission is qualified',
            'qualified_email_body' => <<<'MARKDOWN'
                Dear {{name}},

                Good news: your submission has passed the administrative selection of the Indonesia Corporate Sustainability Award (ICS Award) 2026.

                - **Category:** {{category}}
                - **Initiative:** {{initiative_title}}

                Your submission now moves on to the Desk Evaluation by the Board of Judges. Your files are locked and can no longer be changed. You can follow your status on your dashboard:

                {{dashboard_link}}

                If you have any questions, please contact us at {{contact_email}}.

                Warm regards,<br>
                ICS Award 2026 Committee<br>
                Olahkarsa Group, with IBCSD as Knowledge Partner
                MARKDOWN,
            'needs_revision_email_subject' => 'ICS Award 2026: Your submission needs revision',
            'needs_revision_email_body' => <<<'MARKDOWN'
                Dear {{name}},

                Our committee has reviewed your submission for the Indonesia Corporate Sustainability Award (ICS Award) 2026 and needs you to revise it before it can proceed.

                - **Category:** {{category}}
                - **Initiative:** {{initiative_title}}

                **Notes from the committee:**

                {{revision_note}}

                Please submit your revised files before **{{revision_deadline}}**. Revisions received after this deadline cannot be considered. You can see the details on your dashboard:

                {{dashboard_link}}

                If you have any questions, please contact us at {{contact_email}}.

                Warm regards,<br>
                ICS Award 2026 Committee<br>
                Olahkarsa Group, with IBCSD as Knowledge Partner
                MARKDOWN,
            'disqualified_email_subject' => 'ICS Award 2026: Update on your submission',
            'disqualified_email_body' => <<<'MARKDOWN'
                Dear {{name}},

                Thank you for your interest in the Indonesia Corporate Sustainability Award (ICS Award) 2026. After reviewing your submission, our committee has decided that it does not meet the administrative requirements.

                - **Category:** {{category}}
                - **Initiative:** {{initiative_title}}

                **Reason:**

                {{disqualified_reason}}

                If you have any questions, please contact us at {{contact_email}}.

                Warm regards,<br>
                ICS Award 2026 Committee<br>
                Olahkarsa Group, with IBCSD as Knowledge Partner
                MARKDOWN,
            'finalist_announcement_email_subject' => 'ICS Award 2026: You are a Top 5 finalist',
            'finalist_announcement_email_body' => <<<'MARKDOWN'
                Dear {{name}},

                Congratulations! Your initiative has been selected as one of the Top 5 finalists of the Indonesia Corporate Sustainability Award (ICS Award) 2026.

                - **Category:** {{category}}
                - **Initiative:** {{initiative_title}}

                As a finalist, you will present your initiative to the Board of Judges in a pitching session.

                **Pitching schedule:** {{pitching_schedule}}

                Your schedule and any updates are shown on your dashboard:

                {{dashboard_link}}

                If you have any questions, please contact us at {{contact_email}}.

                Warm regards,<br>
                ICS Award 2026 Committee<br>
                Olahkarsa Group, with IBCSD as Knowledge Partner
                MARKDOWN,
            'awarding_invitation_email_subject' => 'ICS Award 2026: Invitation to the Awarding Night',
            'awarding_invitation_email_body' => <<<'MARKDOWN'
                Dear {{name}},

                As a finalist of the Indonesia Corporate Sustainability Award (ICS Award) 2026, you are cordially invited to the Awarding Night, where the winners of every category will be announced.

                - **Category:** {{category}}
                - **Initiative:** {{initiative_title}}
                - **When & where:** {{awarding_night}}

                The details are also shown on your dashboard:

                {{dashboard_link}}

                If you have any questions, please contact us at {{contact_email}}.

                Warm regards,<br>
                ICS Award 2026 Committee<br>
                Olahkarsa Group, with IBCSD as Knowledge Partner
                MARKDOWN,
            'winner_announcement_email_subject' => 'ICS Award 2026: Congratulations on your {{award}} award',
            'winner_announcement_email_body' => <<<'MARKDOWN'
                Dear {{name}},

                Congratulations! Your initiative received the **{{award}}** award at the Indonesia Corporate Sustainability Award (ICS Award) 2026.

                - **Category:** {{category}}
                - **Initiative:** {{initiative_title}}

                Thank you for your commitment to sustainability. You can see your result on your dashboard:

                {{dashboard_link}}

                If you have any questions, please contact us at {{contact_email}}.

                Warm regards,<br>
                ICS Award 2026 Committee<br>
                Olahkarsa Group, with IBCSD as Knowledge Partner
                MARKDOWN,
            'terms_organization' => implode("\n", [
                '[Placeholder] The submitted initiative has been running for 1–3 years.',
                '[Placeholder] The submitted initiative is original and belongs to the registering organization.',
                '[Placeholder] The organization is responsible for the accuracy of all submitted data.',
                '[Placeholder] Late submissions do not meet the qualification requirements.',
                '[Placeholder] The committee may disqualify any submission that breaches these terms.',
                '[Placeholder] The decision of the Board of Judges is final.',
            ]),
            'terms_individual' => implode("\n", [
                '[Placeholder] The nominee currently holds the stated management position.',
                '[Placeholder] The submitted leadership initiative is original.',
                '[Placeholder] The nominee is responsible for the accuracy of all submitted data.',
                '[Placeholder] The committee may disqualify any submission that breaches these terms.',
                '[Placeholder] The decision of the Board of Judges is final.',
            ]),
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
