<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AITerminalController extends AbstractController
{
    private const GEMINI_API_KEY = 'AIzaSyBtIyy2lgCDZKgD5v40YDXQ_Fwo7ty-ZYY';
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/admin/ai-terminal', name: 'admin_ai_terminal')]
    public function terminal(): Response
    {
        // Check if user is admin
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }

        return $this->render('pages/admin/ai-terminal.html.twig');
    }

    #[Route('/admin/ai-terminal/query', name: 'admin_ai_terminal_query', methods: ['POST'])]
    public function query(Request $request): JsonResponse
    {
        // Check if user is admin
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        if (empty($userMessage)) {
            return $this->json(['error' => 'Message is required'], 400);
        }

        try {
            // First, ask AI to analyze the request and generate admin commands
            $systemPrompt = "You are an AI assistant with admin privileges for SkillHarbor platform. Analyze the user's request and determine if it requires admin actions.

If the user wants to:
- Create a user: Respond with JSON: {\"action\": \"create_user\", \"data\": {\"email\": \"...\", \"username\": \"...\", \"password\": \"...\", \"firstName\": \"...\", \"lastName\": \"...\", \"role\": \"student|professor\"}}
- List users: Respond with JSON: {\"action\": \"list_users\", \"data\": {\"limit\": 10}}
- Find user: Respond with JSON: {\"action\": \"find_user\", \"data\": {\"email\": \"...\" or \"username\": \"...\" or \"id\": \"...\"}}
- Update user: Respond with JSON: {\"action\": \"update_user\", \"data\": {\"identifier\": \"email or username or id\", \"updates\": {\"firstName\": \"...\", \"lastName\": \"...\", etc}}}
- Delete user: Respond with JSON: {\"action\": \"delete_user\", \"data\": {\"identifier\": \"email or username or id\"}}
- Ban user: Respond with JSON: {\"action\": \"ban_user\", \"data\": {\"identifier\": \"email or username or id\"}}
- Unban user: Respond with JSON: {\"action\": \"unban_user\", \"data\": {\"identifier\": \"email or username or id\"}}
- Get statistics: Respond with JSON: {\"action\": \"get_stats\"}

If it's a general question or doesn't require admin action, respond normally with helpful information.

User request: " . $userMessage;

            $aiResponse = $this->callGeminiAPI($systemPrompt);
            
            // Try to parse AI response as JSON command
            $commandData = $this->parseAICommand($aiResponse);
            
            if ($commandData) {
                // Execute the admin command
                $result = $this->executeAdminCommand($commandData);
                return $this->json([
                    'success' => true,
                    'response' => $result
                ]);
            } else {
                // Normal AI response
                return $this->json([
                    'success' => true,
                    'response' => $aiResponse
                ]);
            }
        } catch (\Exception $e) {
            // Log the full error for debugging
            error_log('AI Terminal Error: ' . $e->getMessage());
            
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 200);
        }
    }

    private function parseAICommand(string $response): ?array
    {
        // Try to extract JSON from the response
        if (preg_match('/\{[^}]*"action"[^}]*\}/s', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json && isset($json['action'])) {
                return $json;
            }
        }
        return null;
    }

    private function executeAdminCommand(array $command): string
    {
        $action = $command['action'];
        $data = $command['data'] ?? [];

        switch ($action) {
            case 'create_user':
                return $this->createUser($data);
            
            case 'list_users':
                return $this->listUsers($data);
            
            case 'find_user':
                return $this->findUser($data);
            
            case 'update_user':
                return $this->updateUser($data);
            
            case 'delete_user':
                return $this->deleteUser($data);
            
            case 'ban_user':
                return $this->banUser($data);
            
            case 'unban_user':
                return $this->unbanUser($data);
            
            case 'get_stats':
                return $this->getStats();
            
            default:
                throw new \Exception('Unknown admin action: ' . $action);
        }
    }

    private function createUser(array $data): string
    {
        $email = $data['email'] ?? '';
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? 'SkillHarbor2024';
        $firstName = $data['firstName'] ?? '';
        $lastName = $data['lastName'] ?? '';
        $role = $data['role'] ?? 'student';

        if (empty($email) || empty($username)) {
            throw new \Exception('Email and username are required');
        }

        // Check if user exists
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $email]);
        
        if ($existingUser) {
            throw new \Exception('User with email ' . $email . ' already exists');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setIsActive(true);
        $user->setIsVerified(true);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Insert role
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement(
            'INSERT INTO user_roles (user_id, role, created_at) VALUES (?, ?, NOW())',
            [$user->getId(), $role]
        );

        return "✅ User created successfully!\n\n" .
               "**Details:**\n" .
               "- ID: " . $user->getId() . "\n" .
               "- Email: " . $email . "\n" .
               "- Username: " . $username . "\n" .
               "- Name: " . $firstName . " " . $lastName . "\n" .
               "- Role: " . $role . "\n" .
               "- Password: " . $password . " (temporary)\n\n" .
               "The user can now login to the platform.";
    }

    private function listUsers(array $data): string
    {
        $limit = $data['limit'] ?? 10;
        
        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->setMaxResults($limit)
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            return "No users found in the system.";
        }

        $result = "📋 **User List** (showing " . count($users) . " users):\n\n";
        
        foreach ($users as $user) {
            $status = $user->isActive() ? '🟢 Active' : '🔴 Banned';
            $verified = $user->isVerified() ? '✅' : '❌';
            
            $result .= "**ID: " . $user->getId() . "** - " . $user->getFullName() . "\n";
            $result .= "- Email: " . $user->getEmail() . " $verified\n";
            $result .= "- Username: " . $user->getUsername() . "\n";
            $result .= "- Status: $status\n";
            $result .= "- Created: " . $user->getCreatedAt()->format('Y-m-d H:i') . "\n\n";
        }

        return $result;
    }

    private function findUser(array $data): string
    {
        $user = $this->getUserByIdentifier($data);

        if (!$user) {
            return "❌ User not found.";
        }

        $status = $user->isActive() ? '🟢 Active' : '🔴 Banned';
        $verified = $user->isVerified() ? '✅ Verified' : '❌ Not Verified';

        return "👤 **User Details:**\n\n" .
               "- **ID:** " . $user->getId() . "\n" .
               "- **Email:** " . $user->getEmail() . " " . $verified . "\n" .
               "- **Username:** " . $user->getUsername() . "\n" .
               "- **Name:** " . $user->getFullName() . "\n" .
               "- **Status:** $status\n" .
               "- **Phone:** " . ($user->getPhone() ?? 'N/A') . "\n" .
               "- **Gender:** " . ($user->getGender() ?? 'N/A') . "\n" .
               "- **Country:** " . ($user->getCountry() ?? 'N/A') . "\n" .
               "- **University:** " . ($user->getUniversity() ?? 'N/A') . "\n" .
               "- **Created:** " . $user->getCreatedAt()->format('Y-m-d H:i:s') . "\n" .
               "- **Last Login:** " . ($user->getLastLoginAt() ? $user->getLastLoginAt()->format('Y-m-d H:i:s') : 'Never') . "\n";
    }

    private function updateUser(array $data): string
    {
        $user = $this->getUserByIdentifier($data);

        if (!$user) {
            return "❌ User not found.";
        }

        $updates = $data['updates'] ?? [];
        $updated = [];

        foreach ($updates as $field => $value) {
            switch ($field) {
                case 'firstName':
                    $user->setFirstName($value);
                    $updated[] = "First Name";
                    break;
                case 'lastName':
                    $user->setLastName($value);
                    $updated[] = "Last Name";
                    break;
                case 'email':
                    $user->setEmail($value);
                    $updated[] = "Email";
                    break;
                case 'phone':
                    $user->setPhone($value);
                    $updated[] = "Phone";
                    break;
                case 'gender':
                    $user->setGender($value);
                    $updated[] = "Gender";
                    break;
                case 'country':
                    $user->setCountry($value);
                    $updated[] = "Country";
                    break;
                case 'university':
                    $user->setUniversity($value);
                    $updated[] = "University";
                    break;
                case 'bio':
                    $user->setBio($value);
                    $updated[] = "Bio";
                    break;
            }
        }

        $this->entityManager->flush();

        return "✅ User **" . $user->getUsername() . "** updated successfully!\n\n" .
               "**Updated fields:** " . implode(', ', $updated);
    }

    private function deleteUser(array $data): string
    {
        $user = $this->getUserByIdentifier($data);

        if (!$user) {
            return "❌ User not found.";
        }

        $username = $user->getUsername();
        $email = $user->getEmail();

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return "✅ User **$username** ($email) has been permanently deleted from the system.";
    }

    private function banUser(array $data): string
    {
        $user = $this->getUserByIdentifier($data);

        if (!$user) {
            return "❌ User not found.";
        }

        if (!$user->isActive()) {
            return "ℹ️ User **" . $user->getUsername() . "** is already banned.";
        }

        $user->setIsActive(false);
        $this->entityManager->flush();

        return "🔴 User **" . $user->getUsername() . "** (" . $user->getEmail() . ") has been banned.\n\n" .
               "They will no longer be able to access the platform.";
    }

    private function unbanUser(array $data): string
    {
        $user = $this->getUserByIdentifier($data);

        if (!$user) {
            return "❌ User not found.";
        }

        if ($user->isActive()) {
            return "ℹ️ User **" . $user->getUsername() . "** is not banned.";
        }

        $user->setIsActive(true);
        $this->entityManager->flush();

        return "🟢 User **" . $user->getUsername() . "** (" . $user->getEmail() . ") has been unbanned.\n\n" .
               "They can now access the platform again.";
    }

    private function getStats(): string
    {
        $conn = $this->entityManager->getConnection();

        $totalUsers = $conn->fetchOne('SELECT COUNT(*) FROM users');
        $activeUsers = $conn->fetchOne('SELECT COUNT(*) FROM users WHERE is_active = 1');
        $bannedUsers = $conn->fetchOne('SELECT COUNT(*) FROM users WHERE is_active = 0');
        $verifiedUsers = $conn->fetchOne('SELECT COUNT(*) FROM users WHERE is_verified = 1');
        $recentUsers = $conn->fetchOne('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');

        return "📊 **Platform Statistics:**\n\n" .
               "- **Total Users:** $totalUsers\n" .
               "- **Active Users:** $activeUsers\n" .
               "- **Banned Users:** $bannedUsers\n" .
               "- **Verified Users:** $verifiedUsers\n" .
               "- **New Users (Last 7 days):** $recentUsers\n";
    }

    private function getUserByIdentifier(array $data): ?User
    {
        $identifier = $data['identifier'] ?? $data['email'] ?? $data['username'] ?? $data['id'] ?? null;

        if (!$identifier) {
            throw new \Exception('User identifier is required');
        }

        // Try to find by ID first
        if (is_numeric($identifier)) {
            $user = $this->entityManager->getRepository(User::class)->find((int)$identifier);
            if ($user) return $user;
        }

        // Try email
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);
        if ($user) return $user;

        // Try username
        return $this->entityManager->getRepository(User::class)->findOneBy(['username' => $identifier]);
    }

    private function callGeminiAPI(string $message): string
    {
        $url = self::GEMINI_API_URL . '?key=' . self::GEMINI_API_KEY;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $message]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            ]
        ];

        $ch = curl_init($url);
        
        if ($ch === false) {
            throw new \Exception('Failed to initialize cURL');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        
        // Check for cURL errors
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('cURL error: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            // Try to get error details from response
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
            throw new \Exception('Gemini API error (HTTP ' . $httpCode . '): ' . $errorMessage);
        }

        $result = json_decode($response, true);

        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Invalid response format from Gemini API: ' . json_encode($result));
        }

        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
}
