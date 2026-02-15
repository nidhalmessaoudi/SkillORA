<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private RouterInterface $router
    ) {}

    public function supports(Request $request): ?bool
    {
        // Continue ONLY if the current route is the check route
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($accessToken, $client) {
                /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = $googleUser->getEmail();

                // Check if user already exists
                $existingUser = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $email]);

                if ($existingUser) {
                    return $existingUser;
                }

                // Create new user
                $user = new User();
                $user->setEmail($email);
                
                // Extract name from Google profile
                $fullName = $googleUser->getName();
                $nameParts = explode(' ', $fullName, 2);
                $user->setFirstName($nameParts[0] ?? '');
                $user->setLastName($nameParts[1] ?? '');
                
                // Generate a username from email
                $username = explode('@', $email)[0] . rand(100, 999);
                $user->setUsername($username);
                
                // Set a random password (user won't use it, they'll login via Google)
                $user->setPassword(bin2hex(random_bytes(32)));
                
                $user->setIsActive(true);
                $user->setIsVerified(true); // Email is verified by Google
                
                // Download and save avatar if available
                if ($googleUser->getAvatar()) {
                    // You can implement avatar download here if needed
                    // $this->downloadAndSaveAvatar($user, $googleUser->getAvatar());
                }

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // Insert default role (student)
                $conn = $this->entityManager->getConnection();
                $conn->executeStatement(
                    'INSERT INTO user_roles (user_id, role, created_at) VALUES (?, ?, NOW())',
                    [$user->getId(), 'student']
                );

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        
        // Redirect based on role
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new RedirectResponse($this->router->generate('admin_dashboard'));
        }

        if (in_array('ROLE_PROFESSOR', $user->getRoles(), true)) {
            return new RedirectResponse($this->router->generate('professor_dashboard'));
        }

        // Default to home page
        return new RedirectResponse($this->router->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new RedirectResponse($this->router->generate('auth_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('auth_login'), Response::HTTP_TEMPORARY_REDIRECT);
    }
}
