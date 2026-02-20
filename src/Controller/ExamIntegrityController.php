<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Service\AnswerPlagiarismOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ExamIntegrityController extends AbstractController
{
    #[Route('/exam/answer/{id}/integrity', name: 'exam_answer_integrity', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function integrity(
        Answer $answer,
        Request $request,
        EntityManagerInterface $em,
        AnswerPlagiarismOrchestrator $orchestrator
    ): JsonResponse {
        // ✅ Sécurité: l'étudiant ne peut modifier que sa propre Answer
        $user = $this->getUser();
        if (!$user || !$answer->getStudent() || $answer->getStudent()->getId() !== $user->getId()) {
            return $this->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $payload = json_decode($request->getContent() ?: '{}', true) ?: [];
        $type = (string) ($payload['type'] ?? '');
        $content = isset($payload['content']) ? (string) $payload['content'] : '';

        if ($type === 'paste') {
            $answer->incPasteCount();

            // ✅ IMPORTANT: sauver le texte collé avant analyse
            $content = trim($content);
            if ($content !== '') {
                $answer->setContent($content);
            }
        } elseif ($type === 'tab_switch') {
            $answer->incTabSwitchCount();
        } else {
            return $this->json(['ok' => false, 'error' => 'Invalid type'], 400);
        }

        $em->persist($answer);
        $em->flush();

        // ✅ Cooldown anti-spam (1 analyse / 30s)
        $canRecheck = !$answer->getLastPlagiarismCheckAt()
            || $answer->getLastPlagiarismCheckAt() < new \DateTimeImmutable('-30 seconds');

        if ($type === 'paste' && $canRecheck) {
            $orchestrator->analyzeAndSave($answer);
        }

        // ✅ Debug utile pour tester (tu peux l’enlever après)
        $text = (string) $answer->getContent();

        return $this->json([
            'ok' => true,
            'len' => mb_strlen($text),
            'preview' => mb_substr($text, 0, 120),
            'pasteCount' => $answer->getPasteCount() ?? 0,
            'tabSwitchCount' => $answer->getTabSwitchCount() ?? 0,
            'webPlagiarismPercent' => $answer->getWebPlagiarismPercent(),
            'aiSuspicionPercent' => $answer->getAiSuspicionPercent(),
            'lastPlagiarismCheckAt' => $answer->getLastPlagiarismCheckAt()?->format('Y-m-d H:i:s'),
        ]);
    }
}