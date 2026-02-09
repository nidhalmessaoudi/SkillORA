<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/login', name: 'login_redirect')]
    public function loginRedirect(): Response
    {
        return $this->redirectToRoute('auth_login');
    }

    #[Route('/auth/login', name: 'auth_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirect to admin if already logged in as admin
        if ($this->getUser() && $this->getUser()->isAdmin()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('pages/auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/auth/logout', name: 'auth_logout')]
    public function logout(): void
    {
        // This method can be blank - it will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'auth_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        // If already logged in, redirect to home
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $firstName = $request->request->get('firstname');
            $lastName = $request->request->get('lastname');
            $role = $request->request->get('role', 'student'); // Default to student

            // Validate input
            if (!$email || !$password || !$firstName || !$lastName) {
                $error = 'All fields are required.';
            } else {
                // Check if user already exists
                $existingUser = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $email]);

                if ($existingUser) {
                    $error = 'An account with this email already exists.';
                } else {
                    // Create new user
                    $user = new User();
                    $user->setEmail($email);
                    $user->setUsername(strtolower($firstName . $lastName) . rand(100, 999));
                    $user->setFirstName($firstName);
                    $user->setLastName($lastName);
                    
                    // Hash password
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
                    $user->setPassword($hashedPassword);
                    
                    $user->setIsActive(true);
                    $user->setIsVerified(false);

                    // Save user
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    // Insert role into user_roles table
                    $conn = $this->entityManager->getConnection();
                    $conn->executeStatement(
                        'INSERT INTO user_roles (user_id, role, created_at) VALUES (?, ?, NOW())',
                        [$user->getId(), $role]
                    );

                    // Add success message
                    $this->addFlash('success', 'Account created successfully! Please login.');

                    // Redirect to login page
                    return $this->redirectToRoute('auth_login');
                }
            }
        }

        return $this->render('pages/auth/register.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/forgot-password', name: 'auth_forgot_password')]
    public function forgotPassword(): Response
    {
        return $this->render('pages/auth/forgot-password.html.twig');
    }

    #[Route('/verify-email', name: 'auth_verify_email')]
    public function verifyEmail(): Response
    {
        return $this->render('pages/auth/verify-email.html.twig');
    }

    #[Route('/onboarding', name: 'auth_onboarding')]
    public function onboarding(): Response
    {
        return $this->render('pages/auth/onboarding.html.twig');
    }
}
