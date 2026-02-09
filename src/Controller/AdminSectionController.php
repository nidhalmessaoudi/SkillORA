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

#[Route("/admin/courses/{courseId}/sections")]
class AdminSectionController extends AbstractController
{
    private function getCourseOr404(
        int $courseId,
        EntityManagerInterface $em,
    ): Course {
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course) {
            throw $this->createNotFoundException("Course not found");
        }
        return $course;
    }

    private function getSectionOr404(
        int $courseId,
        int $sectionId,
        EntityManagerInterface $em,
    ): CourseSection {
        $section = $em->getRepository(CourseSection::class)->find($sectionId);
        if (!$section || $section->getCourse()->getId() !== $courseId) {
            throw $this->createNotFoundException(
                "Section not found for this course",
            );
        }
        return $section;
    }

    #[Route("/new", name: "admin_sections_new", methods: ["GET", "POST"])]
    public function new(
        int $courseId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $course = $this->getCourseOr404($courseId, $em);

        $section = new CourseSection();
        $section->setCourse($course);

        $form = $this->createForm(CourseSectionType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($section);
            $em->flush();

            return $this->redirectToRoute("admin_courses_show", [
                "courseId" => $courseId,
            ]);
        }

        return $this->render("pages/admin/sections/new.html.twig", [
            "course" => $course,
            "form" => $form->createView(),
        ]);
    }

    #[Route("/{sectionId}", name: "admin_sections_show", methods: ["GET"])]
    public function show(
        int $courseId,
        int $sectionId,
        EntityManagerInterface $em,
    ): Response {
        $course = $this->getCourseOr404($courseId, $em);
        $section = $this->getSectionOr404($courseId, $sectionId, $em);

        return $this->render("pages/admin/sections/show.html.twig", [
            "course" => $course,
            "section" => $section,
        ]);
    }

    #[
        Route(
            "/{sectionId}/edit",
            name: "admin_sections_edit",
            methods: ["GET", "POST"],
        ),
    ]
    public function edit(
        int $courseId,
        int $sectionId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $course = $this->getCourseOr404($courseId, $em);
        $section = $this->getSectionOr404($courseId, $sectionId, $em);

        $form = $this->createForm(CourseSectionType::class, $section);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            return $this->redirectToRoute("admin_courses_show", [
                "courseId" => $courseId,
            ]);
        }

        return $this->render("pages/admin/sections/edit.html.twig", [
            "course" => $course,
            "section" => $section,
            "form" => $form->createView(),
        ]);
    }

    #[
        Route(
            "/{sectionId}/delete",
            name: "admin_sections_delete",
            methods: ["POST"],
        ),
    ]
    public function delete(
        int $courseId,
        int $sectionId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $section = $this->getSectionOr404($courseId, $sectionId, $em);

        if (
            $this->isCsrfTokenValid(
                "delete_section_" . $section->getId(),
                (string) $request->request->get("_token"),
            )
        ) {
            $em->remove($section);
            $em->flush();
        }

        return $this->redirectToRoute("admin_courses_show", [
            "courseId" => $courseId,
        ]);
    }
}
