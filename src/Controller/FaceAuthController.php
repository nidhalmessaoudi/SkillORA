<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class FaceAuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage
    ) {}

    #[Route('/auth/face-login', name: 'auth_face_login')]
    public function faceLogin(): Response
    {
        // If already logged in, redirect to home
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('pages/auth/face-login.html.twig');
    }

    #[Route('/auth/face-authenticate', name: 'auth_face_authenticate', methods: ['POST'])]
    public function authenticateWithFace(Request $request): JsonResponse
    {
        // Custom logger
        $logFile = __DIR__ . '/../../var/log/face_auth.log';
        
        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $log = function($message) use ($logFile) {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
        };
        
        try {
            $log("Face authentication attempt started");
            
            $data = json_decode($request->getContent(), true);
            $capturedFaceData = $data['face_data'] ?? null;

            if (!$capturedFaceData) {
                $log("Face authentication failed: No face data provided");
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No face data provided'
                ], 400);
            }

            // Parse the captured face data
            $captured = json_decode($capturedFaceData, true);
            $log("Captured data after json_decode: " . ($captured ? 'valid' : 'NULL'));
            
            if (!$captured || !isset($captured['descriptors']) || empty($captured['descriptors'])) {
                $log("Face authentication failed: Invalid face data format - captured=" . ($captured ? 'yes' : 'no') . ", has descriptors=" . (isset($captured['descriptors']) ? 'yes' : 'no'));
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid face data format'
                ], 400);
            }

            $log("Face descriptors received: " . count($captured['descriptors']) . " descriptors");

            // Find all users with Face ID enabled
            $users = $this->entityManager->getRepository(User::class)
                ->findBy(['faceIdEnabled' => true]);

            if (empty($users)) {
                $log("Face authentication failed: No Face ID users in database");
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No Face ID users found. Please register with Face ID first.'
                ], 404);
            }

            $log("Comparing against " . count($users) . " Face ID users");

            // Try to match face with stored faces
            $bestMatch = null;
            $bestSimilarity = 0;
            $secondBestSimilarity = 0;

            foreach ($users as $user) {
                $storedFaceData = $user->getFaceData();
                
                if (!$storedFaceData) {
                    continue;
                }

                $stored = json_decode($storedFaceData, true);
                
                if (!$stored || !isset($stored['descriptors'])) {
                    continue;
                }

                // Compare face descriptors
                $similarity = $this->compareFaceDescriptors($captured['descriptors'], $stored['descriptors']);
                
                $log("User {$user->getEmail()}: similarity = " . round($similarity * 100, 2) . "%");

                if ($similarity > $bestSimilarity) {
                    // Demote current best to second best
                    $secondBestSimilarity = $bestSimilarity;
                    $bestSimilarity = $similarity;
                    $bestMatch = $user;
                } elseif ($similarity > $secondBestSimilarity) {
                    $secondBestSimilarity = $similarity;
                }
            }

            $log("Best match: " . ($bestMatch ? $bestMatch->getEmail() : 'none') . " with " . round($bestSimilarity * 100, 2) . "% similarity");
            $log("Second best similarity: " . round($secondBestSimilarity * 100, 2) . "%");
            
            $confidenceGap = $bestSimilarity - $secondBestSimilarity;
            $log("Confidence gap: " . round($confidenceGap * 100, 2) . "%");

            // STRICT SECURITY REQUIREMENTS:
            // 1. Minimum 92% similarity threshold (increased from 70% for security)
            // 2. At least 5% confidence gap from second-best match to ensure uniqueness
            $SIMILARITY_THRESHOLD = 0.92;  // 92% minimum match
            $CONFIDENCE_GAP_THRESHOLD = 0.05; // 5% minimum gap from next best match
            
            if ($bestMatch && $bestSimilarity >= $SIMILARITY_THRESHOLD) {
                // Additional security: check confidence gap if there are multiple users
                if (count($users) > 1 && $confidenceGap < $CONFIDENCE_GAP_THRESHOLD) {
                    $log("Face authentication failed: Match not unique enough (gap: " . round($confidenceGap * 100, 2) . "%)");
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Face match is ambiguous. Please use regular login for security.',
                        'similarity' => round($bestSimilarity * 100, 2)
                    ], 401);
                }
                // Check if user is active and verified
                if (!$bestMatch->isActive()) {
                    $log("Face authentication failed: User account suspended");
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Your account has been suspended'
                    ], 403);
                }

                if (!$bestMatch->isVerified()) {
                    $log("Face authentication failed: Email not verified");
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Please verify your email before logging in'
                    ], 403);
                }

                // Update last login
                $bestMatch->setLastLoginAt(new \DateTime());
                $this->entityManager->flush();

                // Store user ID in session for authentication
                $request->getSession()->set('_security.main.target_path', $this->getRedirectUrl($bestMatch));
                $request->getSession()->set('face_auth_user_id', $bestMatch->getId());
                $request->getSession()->set('face_auth_success', true);

                $log("Face authentication successful for user: " . $bestMatch->getEmail());

                return new JsonResponse([
                    'success' => true,
                    'similarity' => round($bestSimilarity * 100, 2),
                    'user' => [
                        'id' => $bestMatch->getId(),
                        'email' => $bestMatch->getEmail(),
                        'firstName' => $bestMatch->getFirstName(),
                        'lastName' => $bestMatch->getLastName()
                    ],
                    'redirect_url' => $this->generateUrl('auth_face_complete')
                ]);
            }

            // If we get here, either no match or similarity too low
            if ($bestMatch) {
                $log("Face authentication failed: Similarity too low (" . round($bestSimilarity * 100, 2) . "% < " . ($SIMILARITY_THRESHOLD * 100) . "% required)");
            } else {
                $log("Face authentication failed: No face match found");
            }
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Face not recognized. Please ensure good lighting and try again, or use regular login.',
                'similarity' => round($bestSimilarity * 100, 2),
                'threshold' => round($SIMILARITY_THRESHOLD * 100, 2)
            ], 401);

        } catch (\Exception $e) {
            $log("Face authentication EXCEPTION: " . $e->getMessage());
            $log("Stack trace: " . $e->getTraceAsString());
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred during face recognition',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compare face descriptors using perceptual hash matching
     */
    private function compareFaceDescriptors(array $capturedDescriptors, array $storedDescriptors): float
    {
        if (empty($capturedDescriptors) || empty($storedDescriptors)) {
            return 0.0;
        }

        $maxSimilarity = 0;

        foreach ($capturedDescriptors as $captured) {
            foreach ($storedDescriptors as $stored) {
                $similarity = $this->compareDescriptorPair($captured, $stored);
                if ($similarity > $maxSimilarity) {
                    $maxSimilarity = $similarity;
                }
            }
        }

        return $maxSimilarity;
    }

    /**
     * Compare two descriptor objects
     */
    private function compareDescriptorPair(array $desc1, array $desc2): float
    {
        if (!isset($desc1['hash']) || !isset($desc2['hash'])) {
            return 0.0;
        }

        // Compare perceptual hashes (Hamming distance)
        $hashSim = $this->hammingSimilarity($desc1['hash'], $desc2['hash']);
        
        // Compare grayscale signatures
        $graySim = 0;
        if (isset($desc1['grayscale']) && isset($desc2['grayscale'])) {
            $graySim = $this->arrayRMSE($desc1['grayscale'], $desc2['grayscale']);
        }
        
        // Compare edge signatures
        $edgeSim = 0;
        if (isset($desc1['edges']) && isset($desc2['edges'])) {
            $edgeSim = $this->hammingSimilarity($desc1['edges'], $desc2['edges']);
        }
        
        // Weighted combination
        $similarity = ($hashSim * 0.4) + ($graySim * 0.35) + ($edgeSim * 0.25);
        
        return $similarity;
    }

    /**
     * Calculate Hamming similarity (percentage of matching bits)
     */
    private function hammingSimilarity(string $str1, string $str2): float
    {
        if (strlen($str1) !== strlen($str2)) {
            return 0.0;
        }
        
        $matches = 0;
        $length = strlen($str1);
        
        for ($i = 0; $i < $length; $i++) {
            if ($str1[$i] === $str2[$i]) {
                $matches++;
            }
        }
        
        return $matches / $length;
    }

    /**
     * Array similarity using RMSE
     */
    private function arrayRMSE(array $arr1, array $arr2): float
    {
        if (count($arr1) !== count($arr2)) {
            return 0.0;
        }
        
        $sumSquaredDiff = 0;
        $count = count($arr1);
        
        for ($i = 0; $i < $count; $i++) {
            $diff = $arr1[$i] - $arr2[$i];
            $sumSquaredDiff += $diff * $diff;
        }
        
        $rmse = sqrt($sumSquaredDiff / $count);
        
        // Convert RMSE to similarity (0-1)
        $maxRMSE = 255;
        $similarity = 1 - min($rmse / $maxRMSE, 1);
        
        return $similarity;
    }

    #[Route('/auth/face-complete', name: 'auth_face_complete')]
    public function completeFaceAuth(Request $request): Response
    {
        $session = $request->getSession();
        $userId = $session->get('face_auth_user_id');
        $success = $session->get('face_auth_success');

        if (!$userId || !$success) {
            return $this->redirectToRoute('auth_login');
        }

        // Get the user
        $user = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        // Clear face auth session data
        $session->remove('face_auth_user_id');
        $session->remove('face_auth_success');

        // Manually authenticate the user
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);
        $session->set('_security_main', serialize($token));

        // Redirect based on role
        return $this->redirect($this->getRedirectUrl($user));
    }

    /**
     * Get redirect URL based on user role
     */
    private function getRedirectUrl(User $user): string
    {
        // Get user role from database
        $conn = $this->entityManager->getConnection();
        $result = $conn->executeQuery(
            'SELECT role FROM user_roles WHERE user_id = ?',
            [$user->getId()]
        );
        $roleData = $result->fetchAssociative();

        if ($roleData && $roleData['role'] === 'admin') {
            return $this->generateUrl('admin_dashboard');
        } elseif ($roleData && $roleData['role'] === 'professor') {
            return $this->generateUrl('professor_dashboard');
        }

        return $this->generateUrl('app_home');
    }
}
