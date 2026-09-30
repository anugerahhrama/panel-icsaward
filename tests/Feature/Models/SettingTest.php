<?php

use App\Models\Setting;

test('a stored value can be read back by key', function () {
    Setting::put('contact_email', 'panitia@example.com');
    Setting::put('contact_email', 'committee@example.com');

    expect(Setting::get('contact_email'))->toBe('committee@example.com')
        ->and(Setting::query()->where('key', 'contact_email')->count())->toBe(1);
});

test('reading a missing key falls back to the default', function () {
    expect(Setting::get('missing_key', 'fallback'))->toBe('fallback')
        ->and(Setting::get('missing_key'))->toBeNull();
});
