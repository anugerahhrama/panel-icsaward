<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SettingFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value', 'type'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /**
     * Competition dates are entered and communicated in Western Indonesian Time.
     */
    public const string EVENT_TIMEZONE = 'Asia/Jakarta';

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Read several settings in one query; missing keys come back as null.
     *
     * @param  list<string>  $keys
     * @return array<string, string|null>
     */
    public static function many(array $keys): array
    {
        $values = static::query()->whereIn('key', $keys)->pluck('value', 'key');

        return collect($keys)->mapWithKeys(fn (string $key): array => [$key => $values->get($key)])->all();
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Read a secret stored with `putEncrypted()`; missing or unreadable values (e.g. after an `APP_KEY` change) come back as null.
     */
    public static function getEncrypted(string $key): ?string
    {
        $value = static::get($key);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Store a secret encrypted with the app key; null clears it.
     */
    public static function putEncrypted(string $key, ?string $value): void
    {
        static::put($key, $value === null ? null : Crypt::encryptString($value));
    }

    /**
     * Read a `YYYY-MM-DD` setting as the first second of that day in the event timezone.
     */
    public static function startOfDay(string $key): ?CarbonImmutable
    {
        return self::date($key)?->startOfDay();
    }

    /**
     * Read a `YYYY-MM-DD` setting as the last second of that day in the event timezone.
     */
    public static function endOfDay(string $key): ?CarbonImmutable
    {
        return self::date($key)?->endOfDay();
    }

    /**
     * Parse a `YYYY-MM-DD` setting as midnight in the event timezone; missing or invalid values come back as null.
     */
    private static function date(string $key): ?CarbonImmutable
    {
        $value = static::get($key);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value, self::EVENT_TIMEZONE) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the public URL of a file an admin uploaded for a setting (e.g. a downloadable template).
     */
    public static function publicFileUrl(string $key): ?string
    {
        $path = static::get($key);

        return $path === null || $path === '' ? null : Storage::disk('public')->url($path);
    }
}
