<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Check if user is banned
        if ($user->isBanned()) {
            throw new CustomUserMessageAccountStatusException(
                'Your account has been banned. Please contact support for more information.'
            );
        }

        // Check if user is active
        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Your account is not active. Please contact support.'
            );
        }

        // Check if email is verified
        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Please verify your email address before signing in. Check your inbox for the verification link.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Additional post-authentication checks can be added here if needed
    }
}
