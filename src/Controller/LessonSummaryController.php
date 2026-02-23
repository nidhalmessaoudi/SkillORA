<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Service\LessonSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LessonSummaryController extends AbstractController
{
    #[Route(
        '/courses/{courseId}/lessons/{lessonId}/summary',
        name: 'lesson_summary',
        methods: ['POST'],
        requirements: ['courseId' => '\\d+', 'lessonId' => '\\d+']
    )]
    public function summarize(
        int $courseId,
        int $lessonId,
        Request $request,
        EntityManagerInterface $em,
        LessonSummaryService $lessonSummaryService,
    ): JsonResponse {
        try {
            /** @var Course|null $course */
            $course = $em->getRepository(Course::class)->find($courseId);
            if (!$course || $course->getStatus() !== 'published') {
                return $this->json(['ok' => false, 'message' => 'Course not found.'], 404);
            }

            /** @var Lesson|null $lesson */
            $lesson = $em->getRepository(Lesson::class)->find($lessonId);
            if (!$lesson) {
                return $this->json(['ok' => false, 'message' => 'Lesson not found.'], 404);
            }

            $section = $lesson->getSection();
            if (!$section || !$section->getCourse() || $section->getCourse()->getId() !== $course->getId()) {
                return $this->json(['ok' => false, 'message' => 'Lesson not found for this course.'], 404);
            }

            $payload = json_decode($request->getContent(), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            $token = (string) ($request->headers->get('X-CSRF-Token') ?: ($payload['_token'] ?? ''));
            if (!$this->isCsrfTokenValid('lesson_summary_' . $lesson->getId(), $token)) {
                return $this->json(['ok' => false, 'message' => 'Invalid security token. Please refresh and try again.'], 403);
            }

            $summary = $lessonSummaryService->summarizeLesson($lesson);

            return $this->json([
                'ok' => true,
                'summary' => $summary,
                'lessonType' => $lesson->getType(),
            ]);
        } catch (\RuntimeException $e) {
            return $this->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable) {
            return $this->json([
                'ok' => false,
                'message' => 'Unexpected error while generating summary. Please try again.',
            ], 500);
        }
    }
}
