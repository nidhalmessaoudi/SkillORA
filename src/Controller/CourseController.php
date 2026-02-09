<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CourseController extends AbstractController
{
    #[Route('/courses', name: 'courses_index')]
    public function index(Request $request): Response
    {
        $courses = SampleData::getCourses();
        $categories = SampleData::getCategories();
        $instructors = SampleData::getInstructors();

        // Map instructor data to courses
        $coursesWithInstructors = array_map(function($course) use ($instructors) {
            $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
            return array_merge($course, ['instructor' => $instructor]);
        }, $courses);

        // Apply filters (UI only - in real app this would be database queries)
        $selectedCategory = $request->query->get('category');
        $selectedLevel = $request->query->get('level');
        $selectedRating = $request->query->get('rating');
        $sortBy = $request->query->get('sort', 'popular');

        // Filter logic (simplified)
        if ($selectedCategory) {
            $categoryId = array_values(array_filter($categories, fn($c) => $c['slug'] === $selectedCategory))[0]['id'] ?? null;
            if ($categoryId) {
                $coursesWithInstructors = array_filter($coursesWithInstructors, fn($c) => $c['category_id'] === $categoryId);
            }
        }

        if ($selectedLevel) {
            $coursesWithInstructors = array_filter($coursesWithInstructors, fn($c) => strtolower($c['level']) === strtolower($selectedLevel));
        }

        if ($selectedRating) {
            $coursesWithInstructors = array_filter($coursesWithInstructors, fn($c) => $c['rating'] >= floatval($selectedRating));
        }

        // Sort logic
        switch ($sortBy) {
            case 'newest':
                usort($coursesWithInstructors, fn($a, $b) => strtotime($b['updated_at']) - strtotime($a['updated_at']));
                break;
            case 'rating':
                usort($coursesWithInstructors, fn($a, $b) => $b['rating'] <=> $a['rating']);
                break;
            case 'popular':
            default:
                usort($coursesWithInstructors, fn($a, $b) => $b['students_count'] <=> $a['students_count']);
                break;
        }

        return $this->render('pages/courses/index.html.twig', [
            'courses' => array_values($coursesWithInstructors),
            'categories' => $categories,
            'selected_category' => $selectedCategory,
            'selected_level' => $selectedLevel,
            'selected_rating' => $selectedRating,
            'sort_by' => $sortBy,
            'total_count' => count($coursesWithInstructors),
        ]);
    }

    #[Route('/courses/{slug}', name: 'course_show')]
    public function show(string $slug): Response
    {
        $courses = SampleData::getCourses();
        $instructors = SampleData::getInstructors();
        $sections = SampleData::getSections();
        $lessons = SampleData::getLessons();
        $reviews = SampleData::getReviews();

        // Find course by slug
        $course = array_values(array_filter($courses, fn($c) => $c['slug'] === $slug))[0] ?? null;

        if (!$course) {
            throw $this->createNotFoundException('Course not found');
        }

        // Get instructor
        $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
        $course['instructor'] = $instructor;

        // Get course sections and lessons
        $courseSections = array_filter($sections, fn($s) => $s['course_id'] === $course['id']);
        $courseSections = array_map(function($section) use ($lessons) {
            $sectionLessons = array_values(array_filter($lessons, fn($l) => $l['section_id'] === $section['id']));
            usort($sectionLessons, fn($a, $b) => $a['order'] <=> $b['order']);
            return array_merge($section, ['lessons' => $sectionLessons]);
        }, $courseSections);
        usort($courseSections, fn($a, $b) => $a['order'] <=> $b['order']);

        // Get reviews
        $courseReviews = array_values(array_filter($reviews, fn($r) => $r['course_id'] === $course['id']));

        // Calculate rating distribution (mock data)
        $ratingDistribution = [
            5 => 65,
            4 => 22,
            3 => 8,
            2 => 3,
            1 => 2,
        ];

        // Related courses
        $relatedCourses = array_slice(array_values(array_filter($courses, fn($c) => $c['id'] !== $course['id'] && $c['category_id'] === $course['category_id'])), 0, 3);
        $relatedCourses = array_map(function($c) use ($instructors) {
            $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $c['instructor_id']))[0] ?? null;
            return array_merge($c, ['instructor' => $instructor]);
        }, $relatedCourses);

        return $this->render('pages/courses/show.html.twig', [
            'course' => $course,
            'sections' => array_values($courseSections),
            'reviews' => $courseReviews,
            'rating_distribution' => $ratingDistribution,
            'related_courses' => $relatedCourses,
        ]);
    }
}
