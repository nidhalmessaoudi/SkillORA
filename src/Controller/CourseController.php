<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/courses")]
class CourseController extends AbstractController
{
    #[Route("", name: "course_index", methods: ["GET"])]
    public function index(EntityManagerInterface $em): Response
    {
        $courses = $em
            ->getRepository(Course::class)
            ->findBy([], ["id" => "DESC"]);

        return $this->render("course/index.html.twig", [
            "courses" => $courses,
        ]);
    }

    #[Route("/new", name: "course_new", methods: ["GET", "POST"])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($course);
            $em->flush();

            return $this->redirectToRoute("course_index");
        }

        return $this->render("course/new.html.twig", [
            "form" => $form->createView(),
        ]);
    }

    #[Route("/{id}", name: "course_show", methods: ["GET"])]
    public function show(Course $course): Response
    {
        return $this->render("course/show.html.twig", [
            "course" => $course,
        ]);
    }

    #[Route("/{id}/edit", name: "course_edit", methods: ["GET", "POST"])]
    public function edit(
        Course $course,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute("course_index");
        }

        return $this->render("course/edit.html.twig", [
            "course" => $course,
            "form" => $form->createView(),
        ]);
    }

    #[Route("/{id}/delete", name: "course_delete", methods: ["POST"])]
    public function delete(
        Course $course,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (
            $this->isCsrfTokenValid(
                "delete_course_" . $course->getId(),
                (string) $request->request->get("_token"),
            )
        ) {
            $em->remove($course);
            $em->flush();
        }

        return $this->redirectToRoute("course_index");
    }

    // Optional: the future "builder" page to manage sections + lessons nicely
    #[Route("/{id}/builder", name: "course_builder", methods: ["GET"])]
    public function builder(Course $course): Response
    {
        return $this->render("course/builder.html.twig", [
            "course" => $course,
        ]);
    }
}
