<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\CourseSection;
use App\Form\CourseSectionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CourseSectionController extends AbstractController
{
    #[
        Route(
            "/courses/{courseId}/sections/new",
            name: "section_new",
            methods: ["GET", "POST"],
        ),
    ]
    public function new(
        int $courseId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course) {
            throw $this->createNotFoundException("Course not found");
        }

        $section = new CourseSection();
        $section->setCourse($course);

        $form = $this->createForm(CourseSectionType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($section);
            $em->flush();

            return $this->redirectToRoute("course_builder", [
                "id" => $courseId,
            ]);
        }

        return $this->render("section/new.html.twig", [
            "course" => $course,
            "form" => $form->createView(),
        ]);
    }

    #[
        Route(
            "/sections/{id}/edit",
            name: "section_edit",
            methods: ["GET", "POST"],
        ),
    ]
    public function edit(
        CourseSection $section,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $form = $this->createForm(CourseSectionType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute("course_builder", [
                "id" => $section->getCourse()->getId(),
            ]);
        }

        return $this->render("section/edit.html.twig", [
            "section" => $section,
            "form" => $form->createView(),
        ]);
    }

    #[Route("/sections/{id}/delete", name: "section_delete", methods: ["POST"])]
    public function delete(
        CourseSection $section,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $courseId = $section->getCourse()->getId();

        if (
            $this->isCsrfTokenValid(
                "delete_section_" . $section->getId(),
                (string) $request->request->get("_token"),
            )
        ) {
            $em->remove($section);
            $em->flush();
        }

        return $this->redirectToRoute("course_builder", ["id" => $courseId]);
    }
}
