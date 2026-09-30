<?php

namespace App\Http\Controllers\Admin\Participants;

use App\Actions\Submissions\SubmitPaper;
use App\Enums\FilePreviewKind;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionFilePreviewController extends Controller
{
    public const string OFFICE_VIEWER_URL = 'https://view.officeapps.live.com/op/embed.aspx';

    public const int SHARED_LINK_MINUTES = 10;

    /**
     * Preview a submitted file in the admin panel: PDFs and images inline, Office files through Office Online.
     *
     * Office Online fetches the file itself, so it gets a fresh short-lived signed URL on every preview.
     * The signature is relative so it stays valid behind an HTTPS-terminating proxy.
     *
     * @param  'paper'|'statement'  $file
     */
    public function __invoke(Submission $submission, string $file): StreamedResponse|RedirectResponse
    {
        Gate::authorize('downloadFiles', $submission);

        $submitted = $submission->submittedFile($file);
        $disk = Storage::disk(SubmitPaper::DISK);

        abort_if($submitted === null || ! $disk->exists($submitted['path']), 404);

        return match (FilePreviewKind::fromFileName($submitted['name'])) {
            FilePreviewKind::Pdf, FilePreviewKind::Image => $disk->response($submitted['path'], $submitted['name'], [], 'inline'),
            FilePreviewKind::Office => redirect()->away(self::OFFICE_VIEWER_URL.'?src='.rawurlencode(url(URL::temporarySignedRoute(
                'submissions.files.shared',
                now()->addMinutes(self::SHARED_LINK_MINUTES),
                [$submission, $file],
                absolute: false,
            )))),
            FilePreviewKind::Download => $disk->download($submitted['path'], $submitted['name']),
        };
    }
}
