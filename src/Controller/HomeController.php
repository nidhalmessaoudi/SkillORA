<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route("/", name: "app_home")]
    public function index(EntityManagerInterface $em): Response
    {
        $categories = SampleData::getCategories();
        $instructors = SampleData::getInstructors();
        $testimonials = SampleData::getTestimonials();

        $publishedCourses = $em
            ->getRepository(Course::class)
            ->createQueryBuilder("c")
            ->andWhere("c.status = :status")
            ->setParameter("status", "published")
            ->orderBy("c.updatedAt", "DESC")
            ->addOrderBy("c.id", "DESC")
            ->getQuery()
            ->getResult();

        $featuredCourses = array_slice($publishedCourses, 0, 4);
        $bestsellers = array_slice($publishedCourses, 4, 4);

        return $this->render("pages/home/index.html.twig", [
            "featured_courses" => $featuredCourses,
            "bestsellers" => $bestsellers,
            "categories" => $categories,
            "instructors" => array_slice($instructors, 0, 4),
            "testimonials" => $testimonials,
        ]);
    }

    #[Route("/search", name: "courses_search", methods: ["GET"])]
    public function search(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $q = trim((string) $request->query->get("q", ""));

        $results = [];
        $total = 0;

        if ($q !== "") {
            $needle = mb_strtolower($q);

            $qb = $em->getRepository(Course::class)->createQueryBuilder("c");

            $qb->andWhere("c.status = :status")
                ->setParameter("status", "published")
                ->andWhere(
                    $qb
                        ->expr()
                        ->orX(
                            "LOWER(c.title) LIKE :q",
                            "LOWER(c.description) LIKE :q",
                        ),
                )
                ->setParameter("q", "%" . $needle . "%")
                ->orderBy("c.updatedAt", "DESC")
                ->addOrderBy("c.id", "DESC");

            $results = $qb->getQuery()->getResult();
            $total = count($results);
        }

        return $this->render("pages/courses/search.html.twig", [
            "q" => $q,
            "results" => $results,
            "total" => $total,
        ]);
    }
}
