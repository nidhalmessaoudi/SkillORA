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
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes in seconds

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * Comprehensive email validation
     */
    private function validateEmail(string $email): array
    {
        $errors = [];
        
        // Remove whitespace
        $email = trim($email);
        
        // Check if empty
        if (empty($email)) {
            $errors[] = 'Email address is required';
            return $errors;
        }
        
        // Check length
        if (strlen($email) > 180) {
            $errors[] = 'Email address is too long (maximum 180 characters)';
        }
        
        // Basic format check
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        // Check for common typos in popular domains
        $popularDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
        $emailParts = explode('@', $email);
        if (count($emailParts) === 2) {
            $domain = strtolower($emailParts[1]);
            foreach ($popularDomains as $popularDomain) {
                $similarity = 0;
                similar_text($domain, $popularDomain, $similarity);
                if ($similarity > 80 && $domain !== $popularDomain) {
                    $errors[] = "Did you mean {$emailParts[0]}@{$popularDomain}?";
                }
            }
        }
        
        // Check for disposable email domains
        $disposableDomains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com', 'mailinator.com'];
        if (isset($emailParts[1]) && in_array(strtolower($emailParts[1]), $disposableDomains)) {
            $errors[] = 'Disposable email addresses are not allowed';
        }
        
        return $errors;
    }

    /**
     * Advanced password validation with strength checking
     */
    private function validatePassword(string $password, array $context = []): array
    {
        $errors = [];
        
        // Check if empty
        if (empty($password)) {
            $errors[] = 'Password is required';
            return $errors;
        }
        
        // Length check
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (strlen($password) > 128) {
            $errors[] = 'Password is too long (maximum 128 characters)';
        }
        
        // Complexity checks
        $hasUppercase = preg_match('/[A-Z]/', $password);
        $hasLowercase = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecialChar = preg_match('/[^A-Za-z0-9]/', $password);
        
        $strengthScore = 0;
        if ($hasUppercase) $strengthScore++;
        if ($hasLowercase) $strengthScore++;
        if ($hasNumber) $strengthScore++;
        if ($hasSpecialChar) $strengthScore++;
        
        if ($strengthScore < 3) {
            $missing = [];
            if (!$hasUppercase) $missing[] = 'uppercase letter';
            if (!$hasLowercase) $missing[] = 'lowercase letter';
            if (!$hasNumber) $missing[] = 'number';
            if (!$hasSpecialChar) $missing[] = 'special character';
            
            $errors[] = 'Password must include at least 3 of: ' . implode(', ', $missing);
        }
        
        // Check for common weak passwords
        $weakPasswords = ['password', '12345678', 'qwerty', 'password123', 'admin123', 'welcome123'];
        if (in_array(strtolower($password), $weakPasswords)) {
            $errors[] = 'This password is too common and easily guessable';
        }
        
        // Check if password contains user data (for registration)
        if (isset($context['email'])) {
            $emailUsername = explode('@', $context['email'])[0];
            if (stripos($password, $emailUsername) !== false) {
                $errors[] = 'Password should not contain your email address';
            }
        }
        
        if (isset($context['firstname']) && strlen($context['firstname']) > 2) {
            if (stripos($password, $context['firstname']) !== false) {
                $errors[] = 'Password should not contain your first name';
            }
        }
        
        if (isset($context['lastname']) && strlen($context['lastname']) > 2) {
            if (stripos($password, $context['lastname']) !== false) {
                $errors[] = 'Password should not contain your last name';
            }
        }
        
        return $errors;
    }

    /**
     * Validate name fields
     */
    private function validateName(string $name, string $fieldName): array
    {
        $errors = [];
        
        // Trim whitespace
        $name = trim($name);
        
        // Check if empty
        if (empty($name)) {
            $errors[] = ucfirst($fieldName) . ' is required';
            return $errors;
        }
        
        // Length checks
        if (strlen($name) < 2) {
            $errors[] = ucfirst($fieldName) . ' must be at least 2 characters';
        }
        
        if (strlen($name) > 50) {
            $errors[] = ucfirst($fieldName) . ' is too long (maximum 50 characters)';
        }
        
        // Pattern check - only letters, spaces, hyphens, apostrophes
        if (!preg_match("/^[a-zA-ZÀ-ÿ\s'\-]+$/u", $name)) {
            $errors[] = ucfirst($fieldName) . ' can only contain letters, spaces, hyphens, and apostrophes';
        }
        
        // Check for suspicious patterns
        if (preg_match('/\d{3,}/', $name)) {
            $errors[] = ucfirst($fieldName) . ' should not contain multiple consecutive numbers';
        }
        
        return $errors;
    }

    /**
     * Check rate limiting for login attempts
     */
    private function checkLoginRateLimit(Request $request): ?string
    {
        $session = $request->getSession();
        $attempts = $session->get('login_attempts', 0);
        $lastAttempt = $session->get('last_login_attempt', 0);
        $currentTime = time();
        
        // Reset counter if lockout period has passed
        if ($currentTime - $lastAttempt > self::LOCKOUT_DURATION) {
            $session->set('login_attempts', 0);
            return null;
        }
        
        // Check if locked out
        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $remainingTime = self::LOCKOUT_DURATION - ($currentTime - $lastAttempt);
            $minutes = ceil($remainingTime / 60);
            return "Too many failed login attempts. Please try again in {$minutes} minute(s).";
        }
        
        return null;
    }

    /**
     * Record login attempt
     */
    private function recordLoginAttempt(Request $request, bool $success): void
    {
        $session = $request->getSession();
        
        if ($success) {
            // Clear attempts on successful login
            $session->remove('login_attempts');
            $session->remove('last_login_attempt');
        } else {
            // Increment failed attempts
            $attempts = $session->get('login_attempts', 0) + 1;
            $session->set('login_attempts', $attempts);
            $session->set('last_login_attempt', time());
        }
    }

    #[Route('/login', name: 'login_redirect')]
    public function loginRedirect(): Response
    {
        return $this->redirectToRoute('auth_login');
    }

    #[Route('/auth/login', name: 'auth_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // Redirect to appropriate dashboard if already logged in
        if ($this->getUser()) {
            if ($this->getUser()->isAdmin()) {
                return $this->redirectToRoute('admin_dashboard');
            } elseif ($this->isGranted('ROLE_PROFESSOR')) {
                return $this->redirectToRoute('professor_dashboard');
            }
            return $this->redirectToRoute('app_home');
        }

        // Check rate limiting
        $rateLimitError = $this->checkLoginRateLimit($request);
        if ($rateLimitError) {
            return $this->render('pages/auth/login.html.twig', [
                'last_username' => '',
                'error' => (object)['message' => $rateLimitError],
                'field_errors' => ['email' => [$rateLimitError]],
                'rate_limited' => true,
            ]);
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Record failed attempt if there's an error
        if ($error) {
            $this->recordLoginAttempt($request, false);
        }
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Enhanced error messages
        $errorMessage = null;
        $fieldErrors = [];
        if ($error) {
            $errorMessage = match($error->getMessageKey()) {
                'Invalid credentials.' => 'The email or password you entered is incorrect. Please try again.',
                'Username could not be found.' => 'No account found with this email address.',
                'Account is disabled.' => 'Your account has been disabled. Please contact support.',
                default => $error->getMessage(),
            };
            
            // Set field-specific errors for login
            if ($error->getMessageKey() === 'Username could not be found.') {
                $fieldErrors['email'] = ['No account found with this email address.'];
            } else {
                $fieldErrors['password'] = [$errorMessage];
            }
        }

        return $this->render('pages/auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $errorMessage ? (object)['message' => $errorMessage] : null,
            'field_errors' => $fieldErrors,
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

        $errors = [];
        $fieldErrors = [];
        $formData = [];

        if ($request->isMethod('POST')) {
            // Sanitize and retrieve input data
            $email = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');
            $passwordConfirm = $request->request->get('password_confirm', '');
            $firstName = trim($request->request->get('firstname', ''));
            $lastName = trim($request->request->get('lastname', ''));
            $role = $request->request->get('role', 'student');
            
            // Store form data for re-population
            $formData = [
                'email' => $email,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'role' => $role,
            ];

            // ===== Comprehensive Validation =====
            
            // 1. Email Validation
            $emailErrors = $this->validateEmail($email);
            if (!empty($emailErrors)) {
                $errors = array_merge($errors, $emailErrors);
                $fieldErrors['email'] = $emailErrors;
            } else {
                // Check if email already exists (only if email is valid)
                $existingUser = $this->entityManager->getRepository(User::class)
                    ->findOneBy(['email' => strtolower($email)]);

                if ($existingUser) {
                    $emailExistsError = 'An account with this email already exists. <a href="' . $this->generateUrl('auth_login') . '" class="underline font-semibold">Sign in instead?</a>';
                    $errors[] = $emailExistsError;
                    $fieldErrors['email'] = [$emailExistsError];
                }
            }
            
            // 2. First Name Validation
            $firstNameErrors = $this->validateName($firstName, 'first name');
            if (!empty($firstNameErrors)) {
                $errors = array_merge($errors, $firstNameErrors);
                $fieldErrors['firstname'] = $firstNameErrors;
            }
            
            // 3. Last Name Validation
            $lastNameErrors = $this->validateName($lastName, 'last name');
            if (!empty($lastNameErrors)) {
                $errors = array_merge($errors, $lastNameErrors);
                $fieldErrors['lastname'] = $lastNameErrors;
            }
            
            // 4. Password Validation
            $passwordContext = [
                'email' => $email,
                'firstname' => $firstName,
                'lastname' => $lastName,
            ];
            $passwordErrors = $this->validatePassword($password, $passwordContext);
            if (!empty($passwordErrors)) {
                $errors = array_merge($errors, $passwordErrors);
                $fieldErrors['password'] = $passwordErrors;
            }
            
            // 5. Password Confirmation Check
            if (empty($passwordConfirm)) {
                $confirmError = 'Please confirm your password';
                $errors[] = $confirmError;
                $fieldErrors['password_confirm'] = [$confirmError];
            } elseif ($password !== $passwordConfirm) {
                $matchError = 'Passwords do not match. Please make sure both password fields are identical.';
                $errors[] = $matchError;
                $fieldErrors['password_confirm'] = [$matchError];
            }
            
            // 6. Role Validation
            $validRoles = ['student', 'professor'];
            if (!in_array($role, $validRoles)) {
                $errors[] = 'Invalid role selected. Please choose either student or professor.';
                $formData['role'] = 'student'; // Reset to default
            }
            
            // 7. CSRF/Honeypot check (basic bot protection)
            $honeypot = $request->request->get('website', '');
            if (!empty($honeypot)) {
                // This field should be empty (hidden from real users)
                $errors[] = 'Invalid form submission detected.';
            }
            
            // If no errors, create the user
            if (empty($errors)) {
                try {
                    // Create new user
                    $user = new User();
                    $user->setEmail(strtolower($email));
                    
                    // Generate unique username
                    $baseUsername = strtolower($firstName . $lastName);
                    $baseUsername = preg_replace('/[^a-z0-9]/', '', $baseUsername);
                    $username = $baseUsername . rand(100, 999);
                    
                    // Ensure username is unique
                    $counter = 0;
                    while ($this->entityManager->getRepository(User::class)->findOneBy(['username' => $username])) {
                        $username = $baseUsername . rand(1000, 9999);
                        $counter++;
                        if ($counter > 10) {
                            $username = $baseUsername . uniqid();
                            break;
                        }
                    }
                    
                    $user->setUsername($username);
                    $user->setFirstName(ucfirst(strtolower($firstName)));
                    $user->setLastName(ucfirst(strtolower($lastName)));
                    
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

                    // Add success message with user's first name
                    $this->addFlash('success', "Welcome aboard, {$firstName}! Your account has been created successfully. Please sign in to start your learning journey.");

                    // Redirect to login page
                    return $this->redirectToRoute('auth_login');
                    
                } catch (\Exception $e) {
                    // Log the error (in production, use proper logging)
                    $errors[] = 'An unexpected error occurred while creating your account. Please try again. If the problem persists, contact support.';
                    
                    // Roll back transaction if partially completed
                    if ($this->entityManager->getConnection()->isTransactionActive()) {
                        $this->entityManager->getConnection()->rollBack();
                    }
                }
            }
        }

        return $this->render('pages/auth/register.html.twig', [
            'errors' => $errors,
            'field_errors' => $fieldErrors,
            'form_data' => $formData,
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
