<?php

namespace App\Controller;

use App\Entity\Course;
use App\Service\CourseInsightsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class CourseInsightsController extends AbstractController
{
    #[Route(
        '/courses/{courseId}/insights',
        name: 'course_insights',
        methods: ['GET'],
        requirements: ['courseId' => '\\d+']
    )]
    public function insights(
        int $courseId,
        EntityManagerInterface $em,
        CourseInsightsService $courseInsightsService,
    ): JsonResponse {
        /** @var Course|null $course */
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course || $course->getStatus() !== 'published') {
            return $this->json([
                'ok' => false,
                'message' => 'Course not found.',
            ], 404);
        }

        try {
            $insights = $courseInsightsService->getInsightsForCourse($course);

            return $this->json([
                'ok' => true,
                'insights' => $insights,
            ]);
        } catch (\Throwable) {
            return $this->json([
                'ok' => false,
                'message' => 'Unable to load insights right now. Please try again.',
            ], 503);
        }
    }
}

