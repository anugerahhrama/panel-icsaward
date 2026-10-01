<?php

namespace App\Models;

use App\Actions\Submissions\SendSubmissionConfirmation;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $position
 * @property string|null $company_name
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $account_password
 * @property UserRole $role
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'email', 'phone', 'position', 'company_name', 'password'])]
#[Hidden(['password', 'account_password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Mirror the column default so a freshly created user has a role before being reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'participant',
    ];

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * The judge profile this account logs in for.
     *
     * @return HasOne<Judge, $this>
     */
    public function judge(): HasOne
    {
        return $this->hasOne(Judge::class);
    }

    /**
     * Participants verify their email through the personal submission link, so they receive
     * the submission confirmation instead of Fortify's default verification email.
     */
    public function sendEmailVerificationNotification(): void
    {
        $submission = $this->submissions()->latest('id')->first();

        if ($submission === null) {
            parent::sendEmailVerificationNotification();

            return;
        }

        app(SendSubmissionConfirmation::class)->handle($submission);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_password' => 'encrypted',
            'role' => UserRole::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
