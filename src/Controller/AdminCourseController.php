<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route("/admin/courses")]
class AdminCourseController extends AbstractController
{
    #[Route("", name: "admin_courses_index", methods: ["GET"])]
    public function index(EntityManagerInterface $em): Response
    {
        // Allow both ADMIN and PROFESSOR
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESSOR')) {
            throw new AccessDeniedException('Access denied. Admin or Professor access required.');
        }
        
        $courses = $em
            ->getRepository(Course::class)
            ->findBy([], ["createdAt" => "DESC"]);

        return $this->render("pages/admin/courses/index.html.twig", [
            "courses" => $courses,
        ]);
    }

    #[Route("/new", name: "admin_courses_new", methods: ["GET", "POST"])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($course);
            $em->flush();

            return $this->redirectToRoute("admin_courses_show", [
                "courseId" => $course->getId(),
            ]);
        }

        return $this->render("pages/admin/courses/new.html.twig", [
            "form" => $form->createView(),
        ]);
    }

    #[Route("/{courseId}", name: "admin_courses_show", methods: ["GET"])]
    public function show(int $courseId, EntityManagerInterface $em): Response
    {
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course) {
            throw $this->createNotFoundException("Course not found");
        }

        return $this->render("pages/admin/courses/show.html.twig", [
            "course" => $course,
        ]);
    }

    #[
        Route(
            "/{courseId}/edit",
            name: "admin_courses_edit",
            methods: ["GET", "POST"],
        ),
    ]
    public function edit(
        int $courseId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course) {
            throw $this->createNotFoundException("Course not found");
        }

        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($course);
            $em->flush();

            return $this->redirectToRoute("admin_courses_index");
        }

        return $this->render("pages/admin/courses/edit.html.twig", [
            "course" => $course,
            "form" => $form->createView(),
        ]);
    }

    #[
        Route(
            "/{courseId}/delete",
            name: "admin_courses_delete",
            methods: ["POST"],
        ),
    ]
    public function delete(
        int $courseId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course) {
            throw $this->createNotFoundException("Course not found");
        }

        if (
            $this->isCsrfTokenValid(
                "delete_course_" . $course->getId(),
                (string) $request->request->get("_token"),
            )
        ) {
            $em->remove($course);
            $em->flush();
        }

        return $this->redirectToRoute("admin_courses_index");
    }
}
