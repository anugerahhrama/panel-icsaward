<?php

use App\Enums\SubmissionStatus;
use App\Exports\PaperSubmissionsExport;
use App\Exports\RegistrationsExport;
use App\Models\AwardCategory;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

test('participants cannot see or export the participant tables', function () {
    $submission = Submission::factory()->paperSubmitted()->create();

    $this->actingAs($submission->user)->get(route('admin.participants.registrations.index'))->assertForbidden();
    $this->actingAs($submission->user)->get(route('admin.participants.registrations.export'))->assertForbidden();
    $this->actingAs($submission->user)->get(route('admin.participants.papers.index'))->assertForbidden();
    $this->actingAs($submission->user)->get(route('admin.participants.papers.export'))->assertForbidden();
    $this->actingAs($submission->user)->get(route('admin.participants.files.show', [$submission, 'paper']))->assertForbidden();
});

test('admins see every registration with its participant and category', function () {
    $submission = Submission::factory()->create(['initiative_title' => 'Clean Rivers']);
    Submission::factory()->paperSubmitted()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/participants/registrations')
            ->where('submissions.total', 2)
            ->where('filters.sort', '-created_at')
            ->where('filters.per_page', 20)
            ->where('submissions.data.1.initiative_title', 'Clean Rivers')
            ->where('submissions.data.1.name', $submission->user->name)
            ->where('submissions.data.1.category', $submission->awardCategory->name)
            ->has('categories', 2)
            ->has('statuses', count(SubmissionStatus::cases())));
});

test('superadmins receive the participant details and the personal submission link', function () {
    $submission = Submission::factory()->create([
        'initiative_description' => 'Restoring river banks with local communities.',
        'user_id' => User::factory()->unverified(),
    ]);

    $this->actingAs(User::factory()->superadmin()->create())
        ->get(route('admin.participants.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('submissions.data.0.uuid', $submission->uuid)
            ->where('submissions.data.0.initiative_description', 'Restoring river banks with local communities.')
            ->where('submissions.data.0.applicant_type', $submission->awardCategory->applicant_type->value)
            ->where('submissions.data.0.email_verified', false)
            ->where('submissions.data.0.submission_url', route('submissions.show', $submission)));
});

test('admins never receive the personal submission link', function () {
    Submission::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('submissions.data.0.submission_url', null));
});

test('registrations can be searched by title, name or email', function (string $search) {
    $user = User::factory()->create(['name' => 'Rina Kusuma', 'email' => 'rina@example.com', 'company_name' => 'Yayasan Biru']);
    Submission::factory()->for($user)->create(['initiative_title' => 'Clean Rivers']);

    foreach ([['Budi Santoso', 'budi@example.com', 'Solar Schools'], ['Dewi Lestari', 'dewi@example.com', 'Mangrove Watch']] as [$name, $email, $title]) {
        Submission::factory()
            ->for(User::factory()->create(['name' => $name, 'email' => $email, 'company_name' => 'Yayasan Hijau']))
            ->create(['initiative_title' => $title]);
    }

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.registrations.index', ['search' => $search]))
        ->assertInertia(fn ($page) => $page
            ->where('submissions.total', 1)
            ->where('submissions.data.0.email', 'rina@example.com'));
})->with(['clean river', 'RINA', 'rina@example']);

test('registrations can be filtered by category and status', function () {
    $category = AwardCategory::factory()->create();
    $match = Submission::factory()->paperSubmitted()->for($category, 'awardCategory')->create();
    Submission::factory()->for($category, 'awardCategory')->create();
    Submission::factory()->paperSubmitted()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.registrations.index', [
            'category' => $category->id,
            'status' => SubmissionStatus::UnderReview->value,
        ]))
        ->assertInertia(fn ($page) => $page
            ->where('submissions.total', 1)
            ->where('submissions.data.0.id', $match->id));
});

test('registrations are sorted and paginated on the server', function () {
    foreach (['Citra', 'Adi', 'Bayu'] as $name) {
        Submission::factory()->for(User::factory()->state(['name' => $name]))->create();
    }

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.registrations.index', ['sort' => '-name', 'per_page' => 10]))
        ->assertInertia(fn ($page) => $page
            ->where('submissions.data.0.name', 'Citra')
            ->where('submissions.data.2.name', 'Adi')
            ->where('submissions.per_page', 10));
});

test('invalid sort and page size values are rejected', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.registrations.index', ['sort' => 'password', 'per_page' => 1000]))
        ->assertSessionHasErrors(['sort', 'per_page']);
});

test('the paper submissions table only lists uploaded papers with admin download links', function () {
    $submission = Submission::factory()->paperSubmitted()->create();
    Submission::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.papers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/participants/papers')
            ->where('submissions.total', 1)
            ->where('filters.sort', '-paper_uploaded_at')
            ->where('submissions.data.0.paper.name', 'paper.pdf')
            ->where('submissions.data.0.paper.url', route('admin.participants.files.show', [$submission, 'paper']))
            ->where('submissions.data.0.paper.preview_url', route('admin.participants.files.preview', [$submission, 'paper']))
            ->where('submissions.data.0.paper.preview_kind', 'pdf'));
});

test('admins can download a participant paper', function () {
    Storage::fake('local');
    $submission = Submission::factory()->paperSubmitted()->create([
        'paper_path' => 'submissions/abc/stored.pdf',
        'paper_original_name' => 'Our Initiative.pdf',
    ]);
    Storage::disk('local')->put('submissions/abc/stored.pdf', 'paper');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.files.show', [$submission, 'paper']))
        ->assertOk()
        ->assertDownload('Our Initiative.pdf');
});

test('downloading a file that was never uploaded is not found', function () {
    Storage::fake('local');
    $submission = Submission::factory()->create();

    $this->actingAs(User::factory()->superadmin()->create())
        ->get(route('admin.participants.files.show', [$submission, 'paper']))
        ->assertNotFound();
});

test('the registrations export uses the table filters', function () {
    Excel::fake();
    $this->travelTo(now()->setTimezone('UTC')->setDateTime(2026, 10, 1, 3, 30));
    $category = AwardCategory::factory()->create();
    Submission::factory()->for($category, 'awardCategory')->create();
    Submission::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.registrations.export', ['category' => $category->id]))
        ->assertOk();

    Excel::assertDownloaded('registrations-20261001-1030.xlsx', function (RegistrationsExport $export) use ($category) {
        return $export->query()->count() === 1
            && $export->map($export->query()->sole())[6] === $category->name;
    });
});

test('the paper submissions export only includes uploaded papers', function () {
    Excel::fake();
    $this->travelTo(now()->setTimezone('UTC')->setDateTime(2026, 10, 1, 3, 30));
    $submission = Submission::factory()->paperSubmitted()->create();
    Submission::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.participants.papers.export'))
        ->assertOk();

    Excel::assertDownloaded('paper-submissions-20261001-1030.xlsx', function (PaperSubmissionsExport $export) use ($submission) {
        return $export->query()->count() === 1
            && $export->map($export->query()->sole())[7] === route('admin.participants.files.show', [$submission, 'paper']);
    });
});
