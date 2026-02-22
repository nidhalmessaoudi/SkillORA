<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InstructorController extends AbstractController
{
    #[Route('/instructors', name: 'instructors_index')]
    public function index(): Response
    {
        $instructors = SampleData::getInstructors();
        $courses = SampleData::getCourses();

        // Add course count to instructors
        $instructorsWithCourses = array_map(function($instructor) use ($courses) {
            $instructorCourses = array_filter($courses, fn($c) => $c['instructor_id'] === $instructor['id']);
            $instructor['courses'] = count($instructorCourses);
            return $instructor;
        }, $instructors);

        return $this->render('pages/instructors/index.html.twig', [
            'instructors' => $instructorsWithCourses,
        ]);
    }

    #[Route('/instructors/{id}', name: 'instructor_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $instructors = SampleData::getInstructors();
        $courses = SampleData::getCourses();
        $reviews = SampleData::getReviews();
        $levels = SampleData::getLevels();

        $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $id))[0] ?? null;

        if (!$instructor) {
            throw $this->createNotFoundException('Instructor not found');
        }

        // Get instructor's courses with full data
        $instructorCourses = array_values(array_filter($courses, fn($c) => $c['instructor_id'] === $instructor['id']));
        $instructorCourses = array_map(function($course) use ($instructor) {
            $course['instructor'] = $instructor;
            return $course;
        }, $instructorCourses);

        return $this->render('pages/instructors/show.html.twig', [
            'instructor' => $instructor,
            'instructorCourses' => $instructorCourses,
            'reviews' => array_slice($reviews, 0, 5),
            'levels' => $levels,
        ]);
    }

    #[Route('/studio', name: 'studio_index')]
    public function studio(): Response
    {
        $courses = SampleData::getCourses();
        $instructors = SampleData::getInstructors();
        $reviews = SampleData::getReviews();

        // Get current instructor (mock - using first instructor)
        $currentInstructor = $instructors[0];

        // Get instructor's courses with full data
        $instructorCourses = array_values(array_filter($courses, fn($c) => $c['instructor_id'] === $currentInstructor['id']));
        $instructorCourses = array_map(function($course) use ($currentInstructor) {
            $course['instructor'] = $currentInstructor;
            return $course;
        }, $instructorCourses);

        // Mock studio stats
        $studioStats = [
            'totalStudents' => $currentInstructor['students_count'],
            'totalRevenue' => 125430,
            'avgRating' => $currentInstructor['rating'],
            'activeCourses' => count($instructorCourses),
        ];

        return $this->render('pages/studio/index.html.twig', [
            'instructor' => $currentInstructor,
            'instructorCourses' => $instructorCourses,
            'studioStats' => $studioStats,
            'reviews' => array_slice($reviews, 0, 5),
        ]);
    }

    #[Route('/studio/course/create', name: 'studio_course_create')]
    public function createCourse(): Response
    {
        $categories = SampleData::getCategories();

        return $this->render('pages/studio/course_create.html.twig', [
            'categories' => $categories,
        ]);
    }
}
