<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $courses = SampleData::getCourses();
        $categories = SampleData::getCategories();
        $instructors = SampleData::getInstructors();
        $testimonials = SampleData::getTestimonials();

        // Get featured courses
        $featuredCourses = array_filter($courses, fn($c) => $c['is_featured'] ?? false);

        // Get bestsellers
        $bestsellers = array_filter($courses, fn($c) => $c['is_bestseller'] ?? false);

        // Map instructor data to courses
        $coursesWithInstructors = array_map(function($course) use ($instructors) {
            $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
            return array_merge($course, ['instructor' => $instructor]);
        }, $courses);

        return $this->render('pages/home/index.html.twig', [
            'featured_courses' => array_slice(array_values(array_map(function($course) use ($instructors) {
                $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
                return array_merge($course, ['instructor' => $instructor]);
            }, $featuredCourses)), 0, 4),
            'bestsellers' => array_slice(array_values(array_map(function($course) use ($instructors) {
                $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
                return array_merge($course, ['instructor' => $instructor]);
            }, $bestsellers)), 0, 4),
            'categories' => $categories,
            'instructors' => array_slice($instructors, 0, 4),
            'testimonials' => $testimonials,
        ]);
    }
}
