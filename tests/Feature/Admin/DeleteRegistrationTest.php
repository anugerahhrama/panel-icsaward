<?php

use App\Actions\Submissions\SubmitPaper;
use App\Models\JudgeScore;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('superadmins can delete a registration with its files and the participant account', function () {
    Storage::fake(SubmitPaper::DISK);
    $submission = Submission::factory()->paperSubmitted()->create();
    $participant = $submission->user;
    Storage::disk(SubmitPaper::DISK)->put("submissions/{$submission->uuid}/paper.pdf", 'paper');

    $this->actingAs(User::factory()->superadmin()->create())
        ->from(route('admin.participants.registrations.index'))
        ->delete(route('admin.participants.registrations.destroy', $submission))
        ->assertRedirect(route('admin.participants.registrations.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(Submission::query()->whereKey($submission->id)->exists())->toBeFalse()
        ->and(User::withTrashed()->whereKey($participant->id)->exists())->toBeFalse();
    Storage::disk(SubmitPaper::DISK)->assertMissing("submissions/{$submission->uuid}");
});

test('the participant account is kept while it has other registrations', function () {
    Storage::fake(SubmitPaper::DISK);
    $submission = Submission::factory()->create();
    $other = Submission::factory()->for($submission->user)->create();

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.participants.registrations.destroy', $submission));

    expect(Submission::query()->whereKey($submission->id)->exists())->toBeFalse()
        ->and($other->fresh())->not->toBeNull()
        ->and($submission->user->fresh())->not->toBeNull();
});

test('a registration judges have scored cannot be deleted', function () {
    $submission = Submission::factory()->qualified()->create();
    JudgeScore::factory()->for($submission)->create();

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.participants.registrations.destroy', $submission))
        ->assertInertiaFlash('toast.type', 'error');

    expect($submission->fresh())->not->toBeNull();
});

test('only superadmins can delete a registration', function (string $role) {
    $submission = Submission::factory()->create();

    $this->actingAs(User::factory()->{$role}()->create())
        ->delete(route('admin.participants.registrations.destroy', $submission))
        ->assertForbidden();

    expect($submission->fresh())->not->toBeNull();
})->with(['admin', 'judge']);
