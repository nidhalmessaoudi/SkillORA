<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CourseEnrollmentController extends AbstractController
{
    #[Route('/courses/{courseId}/enroll', name: 'course_enroll', methods: ['POST'], requirements: ['courseId' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function enroll(
        int $courseId,
        Request $request,
        EntityManagerInterface $em,
        EnrollmentRepository $enrollmentRepository,
    ): RedirectResponse {
        /** @var Course|null $course */
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course || $course->getStatus() !== 'published') {
            throw $this->createNotFoundException('Course not found.');
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in to enroll.');
        }

        if (!$this->isCsrfTokenValid('enroll_course_' . $course->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('course_show', ['courseId' => $course->getId()]);
        }

        $existing = $enrollmentRepository->findOneByUserAndCourse($user, $course);
        if ($existing) {
            $this->addFlash('info', 'You are already enrolled in this course.');
            return $this->redirectToRoute('course_show', ['courseId' => $course->getId()]);
        }

        $enrollment = new Enrollment();
        $enrollment->setUser($user);
        $enrollment->setCourse($course);
        $enrollment->setEnrolledAt(new \DateTimeImmutable());
        $enrollment->setStatus(Enrollment::STATUS_ACTIVE);
        $enrollment->setProgressPercent(0);

        $em->persist($enrollment);
        $em->flush();

        $this->addFlash('success', 'Enrollment successful. You can now start learning.');

        return $this->redirectToRoute('course_show', ['courseId' => $course->getId()]);
    }
}
