<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\EnrollmentRepository;
use App\Repository\CertificateRepository;
use App\Service\CourseProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MyCoursesController extends AbstractController
{
    #[Route('/my-courses', name: 'my_courses', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(
        EnrollmentRepository $enrollmentRepository,
        CourseProgressService $courseProgressService,
        CertificateRepository $certificateRepository,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $enrollments = $enrollmentRepository->findByUserWithCourse($user);

        $items = [];
        foreach ($enrollments as $enrollment) {
            $totalLessons = $courseProgressService->getTotalLessons($enrollment->getCourse());
            $completedLessons = $courseProgressService->getCompletedLessons($enrollment);
            $progress = $courseProgressService->calculateProgress($enrollment);
            $nextLesson = $courseProgressService->getNextUncompletedLesson($enrollment);
            $certificate = $certificateRepository->findOneBy(['enrollment' => $enrollment]);

            $items[] = [
                'enrollment' => $enrollment,
                'course' => $enrollment->getCourse(),
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress' => $progress,
                'next_lesson' => $nextLesson,
                'certificate' => $certificate,
            ];
        }

        return $this->render('pages/courses/my_courses.html.twig', [
            'items' => $items,
        ]);
    }
}
