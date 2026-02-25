<?php

namespace App\Controller;

use App\Entity\Post;
use App\Service\AIGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AISummarizerController extends AbstractController
{
    #[Route('/community/ai-summarize', name: 'community_ai_summarize', methods: ['POST'])]
    public function summarize(
        Request $request,
        EntityManagerInterface $em,
        AIGeneratorService $ai
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);
        $postId = $data['postId'] ?? null;

        if (!$postId) {
            return $this->json(['ok' => false, 'error' => 'Missing postId'], 400);
        }

        $post = $em->getRepository(Post::class)->find($postId);

        if (!$post) {
            return $this->json(['ok' => false, 'error' => 'Post not found'], 404);
        }

        $length = $data['length'] ?? 'medium';

        $instruction = match($length) {
            'short' => 'Summarize in 1-2 concise sentences.',
            'long'  => 'Provide a detailed structured summary.',
            default => 'Summarize in 3-5 clear sentences.'
        };

        $prompt = $instruction . "\n\nPost:\n" . $post->getContent();

        try {
            $summary = $ai->generateRaw($prompt);

            return $this->json([
                'ok' => true,
                'summary' => trim($summary)
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'ok' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}