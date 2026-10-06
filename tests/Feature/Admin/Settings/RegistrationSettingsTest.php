<?php

use App\Models\Setting;
use App\Models\User;

/**
 * @return array<string, string>
 */
function validRegistrationSettings(): array
{
    return [
        'is_registration_open' => '1',
        'registration_opens_at' => '2026-09-28',
        'registration_deadline' => '2026-10-20',
        'paper_deadline' => '2026-10-21',
        'max_registrations_per_user' => '2',
        'timeline_administrative_selection' => 'Until 23 October 2026',
        'timeline_desk_evaluation' => '13 – 31 October 2026',
        'timeline_finalists_announcement' => '3 – 5 November 2026',
        'timeline_pitching' => '7 – 11 November 2026',
        'timeline_awarding_night' => '',
        'timeline_desk_evaluation_title' => 'Penilaian dewan juri',
        'timeline_desk_evaluation_description' => 'Dewan juri menilai paper Anda.',
        'timeline_pitching_title' => '',
    ];
}

test('participants cannot update registration settings', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('admin.settings.registration.update'), validRegistrationSettings())
        ->assertForbidden();

    expect(Setting::get('registration_deadline'))->toBeNull();
});

test('admins see the current registration settings', function () {
    Setting::put('paper_deadline', '2026-10-19');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.settings.registration.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/registration')
            ->where('settings.is_registration_open', true)
            ->where('settings.paper_deadline', '2026-10-19')
            ->where('settings.timeline_pitching', null)
            ->has('timelineStages', 5)
            ->where('timelineStages.3.titleKey', 'timeline_pitching_title')
            ->where('timelineStages.3.defaultTitle', 'Pitching session'));
});

test('admins can update registration settings', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.registration.update'), validRegistrationSettings())
        ->assertRedirect(route('admin.settings.registration.edit'))
        ->assertSessionHasNoErrors();

    expect(Setting::get('is_registration_open'))->toBe('1')
        ->and(Setting::get('registration_deadline'))->toBe('2026-10-20')
        ->and(Setting::get('paper_deadline'))->toBe('2026-10-21')
        ->and(Setting::get('max_registrations_per_user'))->toBe('2')
        ->and(Setting::get('timeline_desk_evaluation'))->toBe('13 – 31 October 2026')
        ->and(Setting::get('timeline_awarding_night'))->toBeNull()
        ->and(Setting::get('timeline_desk_evaluation_title'))->toBe('Penilaian dewan juri')
        ->and(Setting::get('timeline_desk_evaluation_description'))->toBe('Dewan juri menilai paper Anda.')
        ->and(Setting::get('timeline_pitching_title'))->toBeNull();
});

test('timeline descriptions are limited to 500 characters', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.registration.update'), [
            ...validRegistrationSettings(),
            'timeline_pitching_description' => str_repeat('a', 501),
        ])
        ->assertSessionHasErrors(['timeline_pitching_description' => 'The Pitching session description field must not be greater than 500 characters.']);
});

test('an unchecked registration toggle closes registration', function () {
    $settings = validRegistrationSettings();
    unset($settings['is_registration_open']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.registration.update'), $settings)
        ->assertSessionHasNoErrors();

    expect(Setting::get('is_registration_open'))->toBe('0');
});

test('the registration deadline cannot be before registration opens', function () {
    $this->actingAs(User::factory()->superadmin()->create())
        ->put(route('admin.settings.registration.update'), [
            ...validRegistrationSettings(),
            'registration_deadline' => '2026-09-01',
        ])
        ->assertSessionHasErrors([
            'registration_deadline' => 'The registration deadline field must be a date after or equal to registration opening date.',
        ]);

    expect(Setting::get('registration_deadline'))->toBeNull();
});

test('dates must use the YYYY-MM-DD format', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.registration.update'), [
            ...validRegistrationSettings(),
            'paper_deadline' => '21/10/2026',
        ])
        ->assertSessionHasErrors(['paper_deadline' => 'The paper deadline field must match the format Y-m-d.']);
});
