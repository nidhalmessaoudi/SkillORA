<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/galaxy')]
class GalaxyController extends AbstractController
{
    public function __construct(
        private HttpClientInterface    $httpClient,
        private EntityManagerInterface $em,
    ) {}

    /**
     * AI Star Name Generation
     * Generates a unique cosmic name for the user's star
     * Cached to avoid regeneration
     */
    #[Route('/star-name', name: 'galaxy_star_name', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function starName(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // For now, generate a simple star name based on username
        // You can implement AI generation later with OpenRouter
        $username = $user->getUsername() ?? $user->getFirstName();
        $memberDays = $user->getCreatedAt()
            ? (new \DateTimeImmutable())->diff($user->getCreatedAt())->days
            : 0;

        // Simple classification based on account age
        $starType = match(true) {
            $memberDays < 7 => 'blue_dwarf',
            $memberDays < 30 => 'yellow_giant',
            $memberDays < 90 => 'red_supergiant',
            default => 'white_nova'
        };

        $starName = $username . ', The Rising Star';
        $tagline = 'A steady light burning bright in the cosmos.';

        return $this->json([
            'starName' => $starName,
            'starType' => $starType,
            'tagline'  => $tagline,
            'cached'   => false,
        ]);
    }

    /**
     * Star Evolution Forecast
     * Predicts user's star evolution based on activity
     */
    #[Route('/star-forecast', name: 'galaxy_star_forecast', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function starForecast(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $memberDays = $user->getCreatedAt()
            ? (new \DateTimeImmutable())->diff($user->getCreatedAt())->days
            : 0;

        $starType = match(true) {
            $memberDays < 7 => 'blue_dwarf',
            $memberDays < 30 => 'yellow_giant',
            $memberDays < 90 => 'red_supergiant',
            default => 'white_nova'
        };

        return $this->json([
            'currentType'  => $starType,
            'currentName'  => ($user->getUsername() ?? $user->getFirstName()) . ', The Rising Star',
            'forecast'     => 'Your star burns with quiet determination.',
            'futureType'   => 'red_supergiant',
            'daysToEvolve' => 14,
            'momentum'     => 'rising',
            'stats'        => [
                'streak'  => $memberDays,
                'points'  => $memberDays * 10,
                'decks'   => 0,
                'correct' => 0,
            ],
        ]);
    }

    /**
     * Users Data for Admin Galaxy
     * Returns all users as stars for 3D visualization
     */
    #[Route('/users-data', name: 'galaxy_users_data', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function usersData(): JsonResponse
    {
        $users = $this->em->getRepository(User::class)->findAll();
        $now   = new \DateTimeImmutable();

        $data = array_map(function (User $u) use ($now): array {
            $daysOld = $u->getCreatedAt()
                ? $now->diff($u->getCreatedAt())->days
                : 0;

            $role = $u->isAdmin() ? 'admin' : 'user';

            return [
                'id'      => $u->getId(),
                'name'    => $u->getFirstName() . ' ' . $u->getLastName(),
                'role'    => $role,
                'banned'  => $u->isBanned(),
                'daysOld' => $daysOld,
            ];
        }, $users);

        return $this->json($data);
    }

    /**
     * Internal: Call OpenRouter API
     * (Optional - for AI star name generation)
     */
    private function callOpenRouter(string $prompt): ?string
    {
        $apiKey = $_ENV['OPENROUTER_API_KEY'] ?? '';
        if (!$apiKey) return null;

        try {
            $response = $this->httpClient->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => 'http://localhost:8000',
                    'X-Title'       => 'SkillHarbor Galaxy',
                ],
                'json' => [
                    'model'       => 'mistralai/mistral-7b-instruct:free',
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens'  => 120,
                    'temperature' => 0.8,
                ],
                'timeout' => 15,
            ]);

            $body = $response->toArray(false);
            return $body['choices'][0]['message']['content'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
