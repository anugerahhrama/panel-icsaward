<?php

namespace App\Actions\Submissions;

use App\Mail\SubmissionConfirmation;
use App\Models\Submission;
use Illuminate\Support\Facades\Mail;

class SendSubmissionConfirmation
{
    /**
     * Queue the registration confirmation email carrying the personal submission link.
     *
     * The link doubles as email verification, so this replaces Fortify's default verification email for participants.
     */
    public function handle(Submission $submission): void
    {
        Mail::to($submission->user)->queue(new SubmissionConfirmation($submission));

        $submission->forceFill(['confirmation_sent_at' => now()])->save();
    }
}
