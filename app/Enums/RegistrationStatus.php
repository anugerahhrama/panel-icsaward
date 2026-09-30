<?php

namespace App\Enums;

use App\Models\Setting;

enum RegistrationStatus: string
{
    case Open = 'open';
    case NotYetOpen = 'not_open';
    case Closed = 'closed';

    /**
     * Resolve whether sign up is accepted right now from the admin toggle and the registration dates.
     *
     * Missing dates do not restrict registration.
     */
    public static function current(): self
    {
        if (Setting::get('is_registration_open', '1') === '0') {
            return self::Closed;
        }

        $opensAt = Setting::startOfDay('registration_opens_at');

        if ($opensAt !== null && $opensAt->isFuture()) {
            return self::NotYetOpen;
        }

        $deadline = Setting::endOfDay('registration_deadline');

        if ($deadline !== null && $deadline->isPast()) {
            return self::Closed;
        }

        return self::Open;
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Get the message shown when a sign up attempt is rejected.
     */
    public function rejectionMessage(): string
    {
        return match ($this) {
            self::NotYetOpen => 'Registration is not open yet.',
            default => 'Registration is closed.',
        };
    }
}
