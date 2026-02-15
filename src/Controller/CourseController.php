<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\CourseSection;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

class CourseController extends AbstractController
{
    #[Route("/courses", name: "courses_index")]
    public function index(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $slugger = new AsciiSlugger();

        $selectedCategory = (string) $request->query->get("category", "");
        $sortBy = (string) $request->query->get("sort", "newest");

        // Get all published courses once (we already need them for categories counts)
        $allPublishedCourses = $em
            ->getRepository(Course::class)
            ->findBy(["status" => "published"]);

        // Build category counts
        $counts = [];
        foreach ($allPublishedCourses as $c) {
            $name = trim((string) ($c->getCategory() ?? ""));
            if ($name === "") {
                continue;
            }
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        ksort($counts);

        // Build categories array + resolve selected category slug -> real DB category name
        $categories = [];
        $selectedCategoryName = null;

        foreach ($counts as $name => $count) {
            $slug = strtolower((string) $slugger->slug($name));

            $categories[] = [
                "name" => $name,
                "slug" => $slug,
                "count" => $count,
            ];

            if (
                $selectedCategory !== "" &&
                $slug === strtolower($selectedCategory)
            ) {
                $selectedCategoryName = $name; // exact DB category name (e.g. "IT & Software")
            }
        }

        // Now query courses (published only) + optional category filter (using exact name)
        $qb = $em
            ->getRepository(Course::class)
            ->createQueryBuilder("c")
            ->andWhere("c.status = :status")
            ->setParameter("status", "published");

        if ($selectedCategoryName !== null) {
            $qb->andWhere("c.category = :cat")->setParameter(
                "cat",
                $selectedCategoryName,
            );
        }

        switch ($sortBy) {
            case "oldest":
                $qb->orderBy("c.updatedAt", "ASC");
                break;
            case "title_asc":
                $qb->orderBy("c.title", "ASC");
                break;
            case "title_desc":
                $qb->orderBy("c.title", "DESC");
                break;
            case "newest":
            default:
                $qb->orderBy("c.updatedAt", "DESC");
                break;
        }

        $courses = $qb->getQuery()->getResult();

        return $this->render("pages/courses/index.html.twig", [
            "courses" => $courses,
            "categories" => $categories,
            "selected_category" => $selectedCategory ?: null, // keep as slug for UI
            "sort_by" => $sortBy,
            "total_count" => count($courses),
        ]);
    }

    #[
        Route(
            "/courses/{courseId}",
            name: "course_show",
            requirements: ["courseId" => "\d+"],
        ),
    ]
    public function show(int $courseId, EntityManagerInterface $em): Response
    {
        /** @var Course|null $course */
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course || $course->getStatus() !== "published") {
            throw $this->createNotFoundException("Course not found");
        }

        $sections = $course->getSections()->toArray();
        usort(
            $sections,
            fn(CourseSection $a, CourseSection $b) => ($a->getPosition() ??
                0) <=>
                ($b->getPosition() ?? 0),
        );

        $sectionsView = [];
        foreach ($sections as $section) {
            $lessons = $section->getLessons()->toArray();
            usort(
                $lessons,
                fn(Lesson $a, Lesson $b) => ($a->getPosition() ?? 0) <=>
                    ($b->getPosition() ?? 0),
            );

            $sectionsView[] = [
                "section" => $section,
                "lessons" => $lessons,
            ];
        }

        $related = $em
            ->getRepository(Course::class)
            ->createQueryBuilder("c")
            ->andWhere("c.status = :status")
            ->andWhere("c.category = :cat")
            ->andWhere("c.id != :id")
            ->setParameter("status", "published")
            ->setParameter("cat", $course->getCategory())
            ->setParameter("id", $course->getId())
            ->orderBy("c.updatedAt", "DESC")
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        return $this->render("pages/courses/show.html.twig", [
            "course" => $course,
            "sections" => $sectionsView,
            "related_courses" => $related,
        ]);
    }

    #[
        Route(
            "/courses/{courseId}/lessons/{lessonId}",
            name: "lesson_show",
            requirements: ["courseId" => "\d+", "lessonId" => "\d+"],
        ),
    ]
    public function showLesson(
        int $courseId,
        int $lessonId,
        EntityManagerInterface $em,
    ): Response {
        /** @var Course|null $course */
        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course || $course->getStatus() !== "published") {
            throw $this->createNotFoundException("Course not found");
        }

        /** @var Lesson|null $lesson */
        $lesson = $em->getRepository(Lesson::class)->find($lessonId);
        if (!$lesson) {
            throw $this->createNotFoundException("Lesson not found");
        }

        $section = $lesson->getSection();
        if (
            !$section ||
            !$section->getCourse() ||
            $section->getCourse()->getId() !== $course->getId()
        ) {
            throw $this->createNotFoundException(
                "Lesson not found for this course",
            );
        }

        $sections = $course->getSections()->toArray();
        usort(
            $sections,
            fn(CourseSection $a, CourseSection $b) => ($a->getPosition() ??
                0) <=>
                ($b->getPosition() ?? 0),
        );

        $sectionsView = [];
        $orderedLessons = [];

        foreach ($sections as $s) {
            $lessons = $s->getLessons()->toArray();
            usort(
                $lessons,
                fn(Lesson $a, Lesson $b) => ($a->getPosition() ?? 0) <=>
                    ($b->getPosition() ?? 0),
            );

            foreach ($lessons as $l) {
                $orderedLessons[] = $l;
            }

            $sectionsView[] = [
                "section" => $s,
                "lessons" => $lessons,
            ];
        }

        $currentIndex = null;
        foreach ($orderedLessons as $i => $l) {
            if ($l->getId() === $lesson->getId()) {
                $currentIndex = $i;
                break;
            }
        }

        $prevLesson =
            $currentIndex !== null && $currentIndex > 0
                ? $orderedLessons[$currentIndex - 1]
                : null;

        $nextLesson =
            $currentIndex !== null && $currentIndex < count($orderedLessons) - 1
                ? $orderedLessons[$currentIndex + 1]
                : null;

        return $this->render("pages/lessons/show.html.twig", [
            "course" => $course,
            "section" => $section,
            "lesson" => $lesson,
            "sections" => $sectionsView,
            "prev_lesson" => $prevLesson,
            "next_lesson" => $nextLesson,
        ]);
    }
}
