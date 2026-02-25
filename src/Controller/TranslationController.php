<?php

namespace App\Controller;

use App\Service\TranslationService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class TranslationController extends AbstractController
{
    private TranslationService $translationService;
    private Connection $connection;
    private LoggerInterface $logger;

    public function __construct(
        TranslationService $translationService,
        Connection $connection,
        LoggerInterface $logger
    ) {
        $this->translationService = $translationService;
        $this->connection = $connection;
        $this->logger = $logger;
    }

    #[Route('/community/translate', name: 'community_translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        try {
            // Parse JSON body
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Invalid JSON in translation request', [
                    'error' => json_last_error_msg(),
                    'body' => $request->getContent()
                ]);
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Invalid JSON: ' . json_last_error_msg()
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!$data) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Empty request body'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $type = $data['type'] ?? null;
            $id = $data['id'] ?? null;
            $targetLang = $data['lang'] ?? null;
            $sourceLang = $data['source'] ?? 'auto';

            if (!$type || !$id || !$targetLang) {
                $this->logger->warning('Missing required fields in translation request', [
                    'data' => $data
                ]);
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Missing required fields: type, id, lang'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate type
            if (!in_array($type, ['post', 'reply'], true)) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Invalid type. Must be "post" or "reply"'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Fetch content from database
            try {
                $sql = $type === 'post'
                    ? 'SELECT content FROM post WHERE id = :id'
                    : 'SELECT content FROM reply WHERE id = :id';

                $content = $this->connection->fetchOne($sql, ['id' => (int)$id]);

                if ($content === false || $content === null) {
                    $this->logger->warning('Content not found for translation', [
                        'type' => $type,
                        'id' => $id
                    ]);
                    return new JsonResponse([
                        'ok' => false,
                        'error' => ucfirst($type) . ' not found'
                    ], Response::HTTP_NOT_FOUND);
                }
            } catch (\Exception $e) {
                $this->logger->error('Database error fetching content', [
                    'type' => $type,
                    'id' => $id,
                    'error' => $e->getMessage()
                ]);
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Database error: ' . $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Translate content
            try {
                $translatedContent = $this->translationService->translateForItem(
                    $content,
                    $targetLang,
                    $type,
                    (int)$id,
                    $sourceLang
                );

                $this->logger->info('Translation successful', [
                    'type' => $type,
                    'id' => $id,
                    'targetLang' => $targetLang
                ]);

                return new JsonResponse([
                    'ok' => true,
                    'translated' => $translatedContent
                ]);

            } catch (\Exception $e) {
                $this->logger->error('Translation service error', [
                    'type' => $type,
                    'id' => $id,
                    'targetLang' => $targetLang,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Translation failed: ' . $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Throwable $e) {
            $this->logger->critical('Unexpected error in translation controller', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new JsonResponse([
                'ok' => false,
                'error' => 'Server error: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}