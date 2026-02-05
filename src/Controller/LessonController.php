<?php

namespace App\Controller;

use App\Entity\CourseSection;
use App\Entity\Lesson;
use App\Form\LessonType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LessonController extends AbstractController
{
    #[
        Route(
            "/sections/{sectionId}/lessons/new",
            name: "lesson_new",
            methods: ["GET", "POST"],
        ),
    ]
    public function new(
        int $sectionId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $section = $em->getRepository(CourseSection::class)->find($sectionId);
        if (!$section) {
            throw $this->createNotFoundException("Section not found");
        }

        $lesson = new Lesson();
        $lesson->setSection($section);

        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($lesson);
            $em->flush();

            return $this->redirectToRoute("course_builder", [
                "id" => $section->getCourse()->getId(),
            ]);
        }

        return $this->render("lesson/new.html.twig", [
            "section" => $section,
            "form" => $form->createView(),
        ]);
    }

    #[
        Route(
            "/lessons/{id}/edit",
            name: "lesson_edit",
            methods: ["GET", "POST"],
        ),
    ]
    public function edit(
        Lesson $lesson,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute("course_builder", [
                "id" => $lesson->getSection()->getCourse()->getId(),
            ]);
        }

        return $this->render("lesson/edit.html.twig", [
            "lesson" => $lesson,
            "form" => $form->createView(),
        ]);
    }

    #[Route("/lessons/{id}/delete", name: "lesson_delete", methods: ["POST"])]
    public function delete(
        Lesson $lesson,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $courseId = $lesson->getSection()->getCourse()->getId();

        if (
            $this->isCsrfTokenValid(
                "delete_lesson_" . $lesson->getId(),
                (string) $request->request->get("_token"),
            )
        ) {
            $em->remove($lesson);
            $em->flush();
        }

        return $this->redirectToRoute("course_builder", ["id" => $courseId]);
    }
}
