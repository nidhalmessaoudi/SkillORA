<?php

namespace App\Controller\Api;

use App\Service\GrokClient;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SkilloraChatController extends AbstractController
{
    #[Route('/api/skillora/chat', name: 'api_skillora_chat', methods: ['POST'])]
    public function chat(
        Request $request,
        Connection $db,
        GrokClient $grok,
    ): JsonResponse {

        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getId')) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $userId = (int) $user->getId();

        $payload = json_decode($request->getContent() ?: '{}', true);
        $message = trim((string)($payload['message'] ?? ''));

        if ($message === '' || mb_strlen($message) > 2000) {
            return $this->json(['error' => 'Message invalid.'], 400);
        }

        $sessionId = $db->fetchOne(
            "SELECT id FROM chat_session WHERE user_id = ? ORDER BY id DESC LIMIT 1",
            [$userId]
        );

        if (!$sessionId) {
            $db->executeStatement(
                "INSERT INTO chat_session (user_id) VALUES (?)",
                [$userId]
            );
            $sessionId = (int)$db->lastInsertId();
        } else {
            $sessionId = (int)$sessionId;
        }

        $db->executeStatement(
            "INSERT INTO chat_message (session_id, user_id, role, content)
             VALUES (?, ?, 'user', ?)",
            [$sessionId, $userId, $message]
        );

        $fallbackRefusal = "Je peux répondre uniquement aux questions concernant Skillora.";

        $systemPrompt = "
Tu es l'assistant officiel de Skillora.

RÈGLES STRICTES :
- Tu réponds UNIQUEMENT aux questions liées à Skillora.
- Si la question n'est PAS à propos de Skillora, répond EXACTEMENT :
\"$fallbackRefusal\"
";

        try {
            $reply = $grok->generate($systemPrompt, $message);
        } catch (\Throwable $e) {
            $rawError = $e->getMessage();
            if (($_ENV['APP_ENV'] ?? 'dev') === 'dev') {
                $reply = 'Erreur IA: ' . $rawError;
            } else {
                $reply = "Erreur, réessaie.";
            }
        }

        if ($reply === '') {
            $reply = $fallbackRefusal;
        }

        $db->executeStatement(
            "INSERT INTO chat_message
            (session_id, user_id, role, content, model)
            VALUES (?, ?, 'assistant', ?, ?)",
            [
                $sessionId,
                $userId,
                $reply,
                $_ENV['GROQ_MODEL'] ?? 'llama-3.1-8b-instant'
            ]
        );

        return $this->json([
            'sessionId' => $sessionId,
            'reply' => $reply,
            'isSkillora' => true,
        ]);
    }
}
