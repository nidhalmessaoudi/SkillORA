<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route("/", name: "app_home")]
    public function index(EntityManagerInterface $em): Response
    {
        // keep everything else as-is (from SampleData)
        $categories = SampleData::getCategories();
        $instructors = SampleData::getInstructors();
        $testimonials = SampleData::getTestimonials();

        // UPDATED: fetch courses from DB (published only)
        $publishedCourses = $em
            ->getRepository(Course::class)
            ->createQueryBuilder("c")
            ->andWhere("c.status = :status")
            ->setParameter("status", "published")
            ->orderBy("c.updatedAt", "DESC")
            ->addOrderBy("c.id", "DESC")
            ->getQuery()
            ->getResult();

        // Since we currently removed "is_featured" / "is_bestseller" from real entities,
        // we simply split the latest published courses into 2 blocks.
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
}
