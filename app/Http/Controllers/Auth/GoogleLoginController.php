<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class GoogleLoginController extends Controller
{
    /**
     * Send the participant to Google's consent screen.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Log an existing participant in with the Google account that owns their email.
     *
     * Google has already proven ownership of the email, so an unverified participant is
     * marked verified and two-factor authentication is not requested.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return $this->failed('Google sign-in was cancelled.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException|GuzzleException) {
            return $this->failed('Google sign-in failed. Please try again.');
        }

        $email = $googleUser->getEmail();
        $isEmailVerified = $googleUser instanceof AbstractUser
            && ($googleUser->getRaw()['email_verified'] ?? false) === true;

        if (blank($email) || ! $isEmailVerified) {
            return $this->failed('Your Google account email is not verified.');
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return $this->failed('No account found for this Google email. Please sign up first.');
        }

        if ($user->role !== UserRole::Participant) {
            return $this->failed('Google sign-in is only available for participants. Please log in with your password.');
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Return to the login page with the reason Google sign-in was refused.
     */
    private function failed(string $message): RedirectResponse
    {
        return to_route('login')->withErrors(['google' => $message]);
    }
}
