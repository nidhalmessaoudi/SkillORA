<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\CourseSection;
use App\Entity\Lesson;
use App\Form\LessonType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/admin/courses/{courseId}/sections/{sectionId}/lessons")]
class AdminLessonController extends AbstractController
{
    private function getCourseSectionOr404(
        int $courseId,
        int $sectionId,
        EntityManagerInterface $em,
    ): array {
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course) {
            throw $this->createNotFoundException("Course not found");
        }

        $section = $em->getRepository(CourseSection::class)->find($sectionId);
        if (!$section || $section->getCourse()->getId() !== $courseId) {
            throw $this->createNotFoundException(
                "Section not found for this course",
            );
        }

        return [$course, $section];
    }

    private function getLessonOr404(
        int $sectionId,
        int $lessonId,
        EntityManagerInterface $em,
    ): Lesson {
        $lesson = $em->getRepository(Lesson::class)->find($lessonId);
        if (!$lesson || $lesson->getSection()->getId() !== $sectionId) {
            throw $this->createNotFoundException(
                "Lesson not found for this section",
            );
        }
        return $lesson;
    }

    #[Route("/new", name: "admin_lessons_new", methods: ["GET", "POST"])]
    public function new(
        int $courseId,
        int $sectionId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        [$course, $section] = $this->getCourseSectionOr404(
            $courseId,
            $sectionId,
            $em,
        );

        $lesson = new Lesson();
        $lesson->setSection($section);

        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($lesson);
            $em->flush();

            return $this->redirectToRoute("admin_sections_show", [
                "courseId" => $courseId,
                "sectionId" => $sectionId,
            ]);
        }

        return $this->render("pages/admin/lessons/new.html.twig", [
            "course" => $course,
            "section" => $section,
            "form" => $form->createView(),
        ]);
    }

    #[Route("/{lessonId}", name: "admin_lessons_show", methods: ["GET"])]
    public function show(
        int $courseId,
        int $sectionId,
        int $lessonId,
        EntityManagerInterface $em,
    ): Response {
        [$course, $section] = $this->getCourseSectionOr404(
            $courseId,
            $sectionId,
            $em,
        );
        $lesson = $this->getLessonOr404($section->getId(), $lessonId, $em);

        return $this->render("pages/admin/lessons/show.html.twig", [
            "course" => $course,
            "section" => $section,
            "lesson" => $lesson,
        ]);
    }

    #[
        Route(
            "/{lessonId}/edit",
            name: "admin_lessons_edit",
            methods: ["GET", "POST"],
        ),
    ]
    public function edit(
        int $courseId,
        int $sectionId,
        int $lessonId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        [$course, $section] = $this->getCourseSectionOr404(
            $courseId,
            $sectionId,
            $em,
        );
        $lesson = $this->getLessonOr404($section->getId(), $lessonId, $em);

        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute("admin_lessons_show", [
                "courseId" => $courseId,
                "sectionId" => $sectionId,
                "lessonId" => $lessonId,
            ]);
        }

        return $this->render("pages/admin/lessons/edit.html.twig", [
            "course" => $course,
            "section" => $section,
            "lesson" => $lesson,
            "form" => $form->createView(),
        ]);
    }

    #[
        Route(
            "/{lessonId}/delete",
            name: "admin_lessons_delete",
            methods: ["POST"],
        ),
    ]
    public function delete(
        int $courseId,
        int $sectionId,
        int $lessonId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        [$course, $section] = $this->getCourseSectionOr404(
            $courseId,
            $sectionId,
            $em,
        );
        $lesson = $this->getLessonOr404($section->getId(), $lessonId, $em);

        if (
            $this->isCsrfTokenValid(
                "delete_lesson_" . $lesson->getId(),
                (string) $request->request->get("_token"),
            )
        ) {
            $em->remove($lesson);
            $em->flush();
        }

        return $this->redirectToRoute("admin_sections_show", [
            "courseId" => $courseId,
            "sectionId" => $sectionId,
        ]);
    }
}
