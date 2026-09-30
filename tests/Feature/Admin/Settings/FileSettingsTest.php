<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fileSettingsPayload(array $overrides = []): array
{
    return [
        'paper_allowed_extensions' => ['pdf', 'docx'],
        'paper_max_size_mb' => '25',
        'require_statement_letter' => '0',
        'terms_organization' => "First term\nSecond term",
        'terms_individual' => 'Individual term',
        ...$overrides,
    ];
}

test('participants cannot update file settings', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.settings.files.update'), fileSettingsPayload())
        ->assertForbidden();
});

test('admins see the current templates and upload rules', function () {
    Setting::put('paper_allowed_extensions', 'pdf,pptx');
    Setting::put('require_statement_letter', '1');
    Setting::put('submission_template_path', 'settings/template.pdf');
    Setting::put('submission_template_name', 'Template.pdf');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.settings.files.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/files')
            ->where('settings.paper_allowed_extensions', ['pdf', 'pptx'])
            ->where('settings.require_statement_letter', true)
            ->where('templates.submission_template.name', 'Template.pdf')
            ->where('templates.submission_template.url', Storage::disk('public')->url('settings/template.pdf'))
            ->where('templates.statement_letter_template.url', null));
});

test('admins can update upload rules and terms', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.settings.files.update'), fileSettingsPayload())
        ->assertRedirect(route('admin.settings.files.edit'))
        ->assertSessionHasNoErrors();

    expect(Setting::get('paper_allowed_extensions'))->toBe('pdf,docx')
        ->and(Setting::get('paper_max_size_mb'))->toBe('25')
        ->and(Setting::get('require_statement_letter'))->toBe('0')
        ->and(Setting::get('terms_organization'))->toBe("First term\nSecond term")
        ->and(Setting::get('terms_individual'))->toBe('Individual term');
});

test('uploading a template stores it publicly and replaces the previous file', function () {
    Storage::disk('public')->put('settings/old.pdf', 'old');
    Setting::put('submission_template_path', 'settings/old.pdf');

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.settings.files.update'), fileSettingsPayload([
            'submission_template' => UploadedFile::fake()->create('ICSA Paper Template.docx', 100),
        ]))
        ->assertSessionHasNoErrors();

    $path = Setting::get('submission_template_path');

    expect($path)->toStartWith('settings/')
        ->and(Setting::get('submission_template_name'))->toBe('ICSA Paper Template.docx');
    Storage::disk('public')->assertExists($path);
    Storage::disk('public')->assertMissing('settings/old.pdf');
});

test('a template can be removed', function () {
    Storage::disk('public')->put('settings/letter.pdf', 'letter');
    Setting::put('statement_letter_template_path', 'settings/letter.pdf');
    Setting::put('statement_letter_template_name', 'Letter.pdf');

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.settings.files.update'), fileSettingsPayload(['remove_statement_letter_template' => '1']))
        ->assertSessionHasNoErrors();

    expect(Setting::get('statement_letter_template_path'))->toBeNull()
        ->and(Setting::get('statement_letter_template_name'))->toBeNull();
    Storage::disk('public')->assertMissing('settings/letter.pdf');
});

test('templates must be PDF or Word files', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.settings.files.update'), fileSettingsPayload([
            'submission_template' => UploadedFile::fake()->create('template.exe', 10),
        ]))
        ->assertSessionHasErrors([
            'submission_template' => 'The submission paper template field must be a file of type: pdf, doc, docx.',
        ]);

    expect(Setting::get('submission_template_path'))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('at least one known paper file type must be allowed', function (array $extensions, string $field, string $message) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.settings.files.update'), fileSettingsPayload(['paper_allowed_extensions' => $extensions]))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'none' => [[], 'paper_allowed_extensions', 'The allowed file types field is required.'],
    'unknown' => [['exe'], 'paper_allowed_extensions.0', 'The selected allowed file type is invalid.'],
]);
