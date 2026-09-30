<?php

use App\Enums\FilePreviewKind;
use App\Http\Controllers\Admin\Participants\SubmissionFilePreviewController;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function submissionWithPaper(string $originalName): Submission
{
    Storage::fake('local');
    Storage::disk('local')->put('submissions/abc/stored', 'paper');

    return Submission::factory()->paperSubmitted()->create([
        'paper_path' => 'submissions/abc/stored',
        'paper_original_name' => $originalName,
    ]);
}

function sharedPaperUrl(Submission $submission): string
{
    return url(URL::temporarySignedRoute(
        'submissions.files.shared',
        now()->addMinutes(SubmissionFilePreviewController::SHARED_LINK_MINUTES),
        [$submission, 'paper'],
        absolute: false,
    ));
}

test('the preview kind follows the file extension', function (?string $name, FilePreviewKind $kind) {
    expect(FilePreviewKind::fromFileName($name))->toBe($kind);
})->with([
    ['Paper.PDF', FilePreviewKind::Pdf],
    ['letter.jpeg', FilePreviewKind::Image],
    ['deck.pptx', FilePreviewKind::Office],
    ['report.docx', FilePreviewKind::Office],
    ['archive.zip', FilePreviewKind::Download],
    [null, FilePreviewKind::Download],
]);

test('admins preview a pdf inline', function () {
    $submission = submissionWithPaper('Our Initiative.pdf');

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.files.preview', [$submission, 'paper']))
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
});

test('admins preview office files through office online with a signed link', function () {
    $submission = submissionWithPaper('Our Initiative.pptx');

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.files.preview', [$submission, 'paper']))
        ->assertRedirect();

    $location = $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith(SubmissionFilePreviewController::OFFICE_VIEWER_URL.'?src=')
        ->and($query['src'])->toStartWith(route('submissions.files.shared', [$submission, 'paper']).'?');

    auth()->logout();

    $shared = $this->get($query['src'])->assertOk();

    expect($shared->headers->get('Content-Disposition'))->toStartWith('inline');
});

test('only admins can open the admin preview', function () {
    $submission = submissionWithPaper('paper.pdf');

    $this->actingAs($submission->user)
        ->get(route('admin.participants.files.preview', [$submission, 'paper']))
        ->assertForbidden();

    $this->actingAs(User::factory()->judge()->create())
        ->get(route('admin.participants.files.preview', [$submission, 'paper']))
        ->assertForbidden();
});

test('the shared link rejects missing and expired signatures', function () {
    $submission = submissionWithPaper('deck.pptx');
    $url = sharedPaperUrl($submission);

    $this->get(route('submissions.files.shared', [$submission, 'paper']))->assertForbidden();

    $this->travel(SubmissionFilePreviewController::SHARED_LINK_MINUTES + 1)->minutes();

    $this->get($url)->assertForbidden();
});

test('previewing a file that was never uploaded is not found', function () {
    Storage::fake('local');
    $submission = Submission::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.files.preview', [$submission, 'paper']))
        ->assertNotFound();

    $this->get(sharedPaperUrl($submission))->assertNotFound();
});
