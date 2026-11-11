<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Works out what goes in the "Temporary Password" line of a portal invite.
 *
 * Accounts are sometimes created earlier in the journey (e.g. when a
 * qualified counsellor is accepted) without the password ever being sent.
 * Telling those people to "use your existing password" locked them out, so
 * any account that has never been used to log in gets a fresh temporary
 * password with every invite. Only someone who has really logged in before
 * keeps their own password.
 */
class PortalAccessService
{
    public const EXISTING_PASSWORD_TEXT = 'Use the password you already log in with. Forgotten it? Click "Forgot password?" on the login page.';

    public static function generatePassword(): string
    {
        return Str::random(10) . '!1Aa';
    }

    /**
     * @param string|null $newPassword password just set on a newly created account
     */
    public function passwordForInvite(User $user, ?string $newPassword = null): string
    {
        if ($newPassword) {
            return $newPassword;
        }

        // Never touch the password of a staff or admin login.
        if ($user->isStaff() || $this->hasLoggedInBefore($user)) {
            return self::EXISTING_PASSWORD_TEXT;
        }

        $password = self::generatePassword();
        $user->forceFill(['password' => Hash::make($password)])->save();

        return $password;
    }

    public function hasLoggedInBefore(User $user): bool
    {
        return !empty($user->last_login_at) || $user->tokens()->exists();
    }
}
