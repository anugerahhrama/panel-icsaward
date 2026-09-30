<?php

use App\Enums\SubmissionStatus;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Setting::put('paper_deadline', '2026-10-19');
    Setting::put('paper_allowed_extensions', 'pdf,pptx');
    Setting::put('paper_max_size_mb', '20');
    Setting::put('require_statement_letter', '1');
    $this->travelTo('2026-10-10 10:00:00');
});

/**
 * @return array<string, UploadedFile>
 */
function paperFiles(): array
{
    return [
        'paper' => UploadedFile::fake()->create('paper.pdf', 500, 'application/pdf'),
        'statement_letter' => UploadedFile::fake()->create('statement.pdf', 200, 'application/pdf'),
    ];
}

test('the owner can submit their paper and it is locked for review', function () {
    $submission = Submission::factory()->create();

    $this->actingAs($submission->user)
        ->post(route('submissions.paper.store', $submission), paperFiles())
        ->assertRedirect(route('submissions.show', $submission));

    $submission->refresh();
    expect($submission->status)->toBe(SubmissionStatus::UnderReview)
        ->and($submission->paper_uploaded_at)->not->toBeNull()
        ->and($submission->paper_original_name)->toBe('paper.pdf')
        ->and($submission->statement_original_name)->toBe('statement.pdf');
    Storage::disk('local')->assertExists([$submission->paper_path, $submission->statement_path]);
});

test('another participant cannot submit a paper for the submission', function () {
    $submission = Submission::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('submissions.paper.store', $submission), paperFiles())
        ->assertForbidden();

    expect($submission->fresh()->paper_uploaded_at)->toBeNull();
});

test('a submitted paper cannot be replaced', function () {
    $submission = Submission::factory()->paperSubmitted()->create();

    $this->actingAs($submission->user)
        ->post(route('submissions.paper.store', $submission), paperFiles())
        ->assertSessionHasErrors(['paper' => 'Your paper has already been submitted and can no longer be changed.']);

    expect($submission->fresh()->paper_path)->toBe('submissions/paper.pdf');
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('papers are rejected after the deadline passes in Western Indonesian Time', function () {
    $submission = Submission::factory()->create();
    $this->travelTo('2026-10-19 17:00:00'); // 20 Oct 00:00 WIB

    $this->actingAs($submission->user)
        ->post(route('submissions.paper.store', $submission), paperFiles())
        ->assertSessionHasErrors(['paper' => 'The submission deadline has passed.']);

    expect($submission->fresh()->paper_uploaded_at)->toBeNull();
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('the statement letter is required when the setting is enabled', function () {
    $submission = Submission::factory()->create();

    $this->actingAs($submission->user)
        ->post(route('submissions.paper.store', $submission), ['paper' => paperFiles()['paper']])
        ->assertSessionHasErrors('statement_letter');

    expect($submission->fresh()->paper_uploaded_at)->toBeNull();
});

test('the statement letter is optional when the setting is disabled', function () {
    Setting::put('require_statement_letter', '0');
    $submission = Submission::factory()->create();

    $this->actingAs($submission->user)
        ->post(route('submissions.paper.store', $submission), ['paper' => paperFiles()['paper']])
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->statement_path)->toBeNull()
        ->and($submission->fresh()->paper_uploaded_at)->not->toBeNull();
});

test('papers outside the allowed formats or size are rejected', function (UploadedFile $paper) {
    $submission = Submission::factory()->create();

    $this->actingAs($submission->user)
        ->post(route('submissions.paper.store', $submission), [...paperFiles(), 'paper' => $paper])
        ->assertSessionHasErrors('paper');

    expect($submission->fresh()->paper_uploaded_at)->toBeNull();
})->with([
    'wrong format' => fn () => UploadedFile::fake()->create('paper.docx', 500, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    'too large' => fn () => UploadedFile::fake()->create('paper.pdf', 20 * 1024 + 1, 'application/pdf'),
]);

test('a participant asked for a revision can replace only the paper before the revision deadline', function () {
    $submission = Submission::factory()->needsRevision()->create(['revision_deadline' => '2026-10-22 16:59:59']);
    Storage::disk('local')->put('submissions/paper.pdf', 'old paper');
    Storage::disk('local')->put('submissions/statement.pdf', 'statement');
    $this->travelTo('2026-10-11 10:00:00');

    $this->actingAs($submission->user)
        ->post(route('submissions.paper.store', $submission), [
            'paper' => UploadedFile::fake()->create('revised.pdf', 500, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('submissions.show', $submission));

    $submission->refresh();
    expect($submission->status)->toBe(SubmissionStatus::UnderReview)
        ->and($submission->paper_original_name)->toBe('revised.pdf')
        ->and($submission->paper_uploaded_at->toDateTimeString())->toBe('2026-10-11 10:00:00')
        ->and($submission->statement_path)->toBe('submissions/statement.pdf')
        ->and($submission->revision_note)->toBe('Please use the official template.')
        ->and($submission->revision_deadline->toDateTimeString())->toBe('2026-10-22 16:59:59');
    Storage::disk('local')->assertExists([$submission->paper_path, 'submissions/statement.pdf']);
    Storage::disk('local')->assertMissing('submissions/paper.pdf');
});

test('a revision is accepted after the paper deadline has passed', function () {
    $submission = Submission::factory()->needsRevision()->create(['revision_deadline' => '2026-10-29 16:59:59']);
    $this->travelTo('2026-10-25 10:00:00');

    $this->actingAs($submission->user)
        ->post(route('submissions.paper.store', $submission), ['statement_letter' => paperFiles()['statement_letter']])
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->status)->toBe(SubmissionStatus::UnderReview)
        ->and($submission->fresh()->paper_path)->toBe('submissions/paper.pdf');
});

test('a revision is rejected once the revision deadline has passed', function () {
    $submission = Submission::factory()->needsRevision()->create(['revision_deadline' => '2026-10-22 16:59:59']);
    $this->travelTo('2026-10-22 17:00:00');

    $this->actingAs($submission->user)
        ->post(route('submissions.paper.store', $submission), paperFiles())
        ->assertSessionHasErrors(['paper' => 'The revision deadline has passed.']);

    expect($submission->fresh()->status)->toBe(SubmissionStatus::NeedsRevision);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('a revision needs at least one file', function () {
    $submission = Submission::factory()->needsRevision()->create();

    $this->actingAs($submission->user)
        ->post(route('submissions.paper.store', $submission), [])
        ->assertSessionHasErrors(['paper' => 'Upload at least one revised file.']);

    expect($submission->fresh()->status)->toBe(SubmissionStatus::NeedsRevision);
});
