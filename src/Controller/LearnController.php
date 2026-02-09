<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LearnController extends AbstractController
{
    #[Route('/learn', name: 'learn_index')]
    public function index(): Response
    {
        $user = SampleData::getCurrentUser();
        $courses = SampleData::getCourses();
        $instructors = SampleData::getInstructors();
        $categories = SampleData::getCategories();
        $allBadges = SampleData::getBadges();

        // Add completedLessons to user data for display
        $user['completedLessons'] = 24;

        // Get enrolled courses with progress
        $enrolledCourses = array_filter($courses, fn($c) => in_array($c['id'], $user['enrolled_courses']));
        $enrolledCourses = array_map(function($course) use ($instructors, $user, $categories) {
            $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
            $category = array_values(array_filter($categories, fn($c) => $c['id'] === $course['category_id']))[0] ?? null;
            $isCompleted = in_array($course['id'], $user['completed_courses']);
            return array_merge($course, [
                'instructor' => $instructor,
                'category' => $category['name'] ?? 'General',
                'totalLessons' => $course['lessons_count'],
                'duration' => $course['duration_hours'] . 'h',
                'progress' => $isCompleted ? 100 : rand(15, 85),
                'completed_lessons' => $isCompleted ? $course['lessons_count'] : rand(5, $course['lessons_count'] - 10),
                'is_completed' => $isCompleted,
                'last_accessed' => $isCompleted ? '3 days ago' : rand(1, 24) . ' hours ago',
            ]);
        }, $enrolledCourses);

        // Sort by last accessed (in progress first)
        usort($enrolledCourses, fn($a, $b) => $a['is_completed'] <=> $b['is_completed']);

        // Get recommended courses (not enrolled)
        $recommendedCourses = array_filter($courses, fn($c) => !in_array($c['id'], $user['enrolled_courses']));
        $recommendedCourses = array_map(function($course) use ($instructors, $categories) {
            $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
            $category = array_values(array_filter($categories, fn($c) => $c['id'] === $course['category_id']))[0] ?? null;
            return array_merge($course, [
                'instructor' => $instructor,
                'category' => $category['name'] ?? 'General',
            ]);
        }, $recommendedCourses);

        // Get user badges with additional display data
        $userBadgeIds = $user['badges'];
        $badges = array_filter($allBadges, fn($b) => in_array($b['id'], $userBadgeIds));
        $badges = array_map(function($badge) {
            $colors = [
                'harbor' => 'from-harbor-500 to-harbor-600',
                'warning' => 'from-amber-500 to-amber-600',
                'coral' => 'from-coral-400 to-coral-500',
                'success' => 'from-emerald-500 to-emerald-600',
                'danger' => 'from-red-500 to-red-600',
            ];
            return array_merge($badge, [
                'color' => $colors[$badge['color']] ?? 'from-harbor-500 to-harbor-600',
                'date' => rand(1, 14) . ' days ago',
            ]);
        }, $badges);

        return $this->render('pages/learn/index.html.twig', [
            'user' => $user,
            'enrolledCourses' => array_values($enrolledCourses),
            'recommendedCourses' => array_values($recommendedCourses),
            'badges' => array_values($badges),
            'in_progress_count' => count(array_filter($enrolledCourses, fn($c) => !$c['is_completed'])),
            'completed_count' => count(array_filter($enrolledCourses, fn($c) => $c['is_completed'])),
        ]);
    }

    #[Route('/learn/{courseId}/{lessonId}', name: 'learn_player', requirements: ['courseId' => '\d+', 'lessonId' => '\d+'])]
    public function player(int $courseId, int $lessonId = 1): Response
    {
        $courses = SampleData::getCourses();
        $instructors = SampleData::getInstructors();
        $sections = SampleData::getSections();
        $lessons = SampleData::getLessons();

        // Find course by ID
        $course = array_values(array_filter($courses, fn($c) => $c['id'] === $courseId))[0] ?? null;

        if (!$course) {
            throw $this->createNotFoundException('Course not found');
        }

        $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
        $course['instructor'] = $instructor;

        // Add totalLessons and duration to course for display
        $course['totalLessons'] = $course['lessons_count'];
        $course['duration'] = $course['duration_hours'] . 'h';

        // Get sections with lessons
        $courseSections = array_filter($sections, fn($s) => $s['course_id'] === $course['id']);
        $courseSections = array_map(function($section) use ($lessons) {
            $sectionLessons = array_values(array_filter($lessons, fn($l) => $l['section_id'] === $section['id']));
            usort($sectionLessons, fn($a, $b) => $a['order'] <=> $b['order']);
            // Mock completion status and add type/description
            $sectionLessons = array_map(function($lesson, $index) {
                return array_merge($lesson, [
                    'is_completed' => $index < 3,
                    'type' => 'video',
                    'description' => 'In this lesson, you will learn the key concepts and practical applications related to ' . $lesson['title'] . '.',
                ]);
            }, $sectionLessons, array_keys($sectionLessons));
            // Calculate section duration
            $totalMinutes = array_reduce($sectionLessons, function($carry, $l) {
                $parts = explode(':', $l['duration']);
                return $carry + (int)$parts[0] + ((int)($parts[1] ?? 0) / 60);
            }, 0);
            return array_merge($section, [
                'lessons' => $sectionLessons,
                'duration' => round($totalMinutes) . ' min',
                'completed' => count(array_filter($sectionLessons, fn($l) => $l['is_completed'])),
            ]);
        }, $courseSections);
        usort($courseSections, fn($a, $b) => $a['order'] <=> $b['order']);

        // Current lesson (first uncompleted)
        $currentLesson = null;
        foreach ($courseSections as $section) {
            foreach ($section['lessons'] as $lesson) {
                if (!$lesson['is_completed']) {
                    $currentLesson = $lesson;
                    break 2;
                }
            }
        }
        if (!$currentLesson && !empty($courseSections)) {
            $currentLesson = $courseSections[0]['lessons'][0] ?? null;
        }

        return $this->render('pages/learn/player.html.twig', [
            'course' => $course,
            'sections' => array_values($courseSections),
            'lesson' => $currentLesson,
            'progress' => 35,
            'completed_lessons' => 3,
            'total_lessons' => array_sum(array_map(fn($s) => count($s['lessons']), $courseSections)),
        ]);
    }
}
