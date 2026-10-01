<?php

use App\Http\Controllers\SubmissionFilePreviewController;
use App\Models\AwardCategory;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
});

test('assigned judges preview a pdf inline', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    Storage::disk('local')->put($submission->paper_path, 'paper');

    $response = $this->actingAs($judge->user)
        ->get(route('judge.submissions.files.preview', [$submission, 'paper']))
        ->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline');
});

test('assigned judges preview office files through office online', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create([
        'paper_original_name' => 'deck.pptx',
    ]);
    Storage::disk('local')->put($submission->paper_path, 'paper');

    $response = $this->actingAs($judge->user)
        ->get(route('judge.submissions.files.preview', [$submission, 'paper']))
        ->assertRedirect();

    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($response->headers->get('Location'))->toStartWith(SubmissionFilePreviewController::OFFICE_VIEWER_URL.'?src=')
        ->and($query['src'])->toStartWith(route('submissions.files.shared', [$submission, 'paper']).'?');
});

test('judges cannot preview submissions they are not assigned to or recused from', function (Closure $arrange) {
    $category = categoryWithRubric();
    [$judge, $submission] = $arrange($category);
    Storage::disk('local')->put($submission->paper_path, 'paper');

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.files.preview', [$submission, 'paper']))
        ->assertForbidden();
})->with([
    'unassigned' => fn (AwardCategory $category) => [
        judgeAssignedTo(categoryWithRubric()),
        Submission::factory()->qualified()->for($category, 'awardCategory')->create(),
    ],
    'recused' => fn (AwardCategory $category) => [
        judgeAssignedTo($category, recused: true),
        Submission::factory()->qualified()->for($category, 'awardCategory')->create(),
    ],
]);

test('participants cannot use the judge preview', function () {
    $submission = Submission::factory()->qualified()->create();
    Storage::disk('local')->put($submission->paper_path, 'paper');

    $this->actingAs($submission->user)
        ->get(route('judge.submissions.files.preview', [$submission, 'paper']))
        ->assertForbidden();
});

test('the scoring page links the files to the judge preview', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create([
        'statement_path' => null,
        'statement_original_name' => null,
    ]);

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.paper', [
                'name' => 'paper.pdf',
                'url' => route('judge.submissions.files.show', [$submission, 'paper']),
                'preview_url' => route('judge.submissions.files.preview', [$submission, 'paper']),
                'preview_kind' => 'pdf',
            ])
            ->where('submission.statement', null));
});
