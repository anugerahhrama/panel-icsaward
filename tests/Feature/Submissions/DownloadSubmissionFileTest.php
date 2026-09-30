<?php

use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('the owner can download their paper under its original name', function () {
    $submission = Submission::factory()->paperSubmitted()->create([
        'paper_path' => 'submissions/abc/stored.pdf',
        'paper_original_name' => 'Our Initiative.pdf',
    ]);
    Storage::disk('local')->put('submissions/abc/stored.pdf', 'paper');

    $this->actingAs($submission->user)
        ->get(route('submissions.files.show', [$submission, 'paper']))
        ->assertOk()
        ->assertDownload('Our Initiative.pdf');
});

test('another participant cannot download the files', function () {
    $submission = Submission::factory()->paperSubmitted()->create();
    Storage::disk('local')->put($submission->paper_path, 'paper');

    $this->actingAs(User::factory()->create())
        ->get(route('submissions.files.show', [$submission, 'paper']))
        ->assertForbidden();
});

test('an assigned judge can download the paper of a qualified submission', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    Storage::disk('local')->put($submission->paper_path, 'paper');

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.files.show', [$submission, 'paper']))
        ->assertOk()
        ->assertDownload('paper.pdf');
});

test('a recused judge cannot download the files', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category, recused: true);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    Storage::disk('local')->put($submission->paper_path, 'paper');

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.files.show', [$submission, 'paper']))
        ->assertForbidden();
});

test('guests are redirected to login', function () {
    $submission = Submission::factory()->paperSubmitted()->create();

    $this->get(route('submissions.files.show', [$submission, 'paper']))
        ->assertRedirect(route('login'));
});

test('a file that was never uploaded is not found', function () {
    $submission = Submission::factory()->paperSubmitted()->create([
        'statement_path' => null,
        'statement_original_name' => null,
    ]);

    $this->actingAs($submission->user)
        ->get(route('submissions.files.show', [$submission, 'statement']))
        ->assertNotFound();
});
