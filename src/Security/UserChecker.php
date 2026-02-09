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
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Additional post-authentication checks can be added here if needed
    }
}
