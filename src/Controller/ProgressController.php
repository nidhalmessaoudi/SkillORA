<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProgressController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function profile(): Response
    {
        $user = SampleData::getCurrentUser();
        $badges = SampleData::getBadges();
        $levels = SampleData::getLevels();
        $courses = SampleData::getCourses();
        $instructors = SampleData::getInstructors();

        // Get user badges
        $userBadges = array_filter($badges, fn($b) => in_array($b['id'], $user['badges']));

        // Get user level info
        $currentLevel = array_values(array_filter($levels, fn($l) => $l['level'] === $user['level']))[0] ?? null;
        $nextLevel = array_values(array_filter($levels, fn($l) => $l['level'] === $user['level'] + 1))[0] ?? null;

        // Get enrolled courses with progress
        $enrolledCourses = array_filter($courses, fn($c) => in_array($c['id'], $user['enrolled_courses']));
        $enrolledCourses = array_map(function($course) use ($instructors, $user) {
            $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
            $isCompleted = in_array($course['id'], $user['completed_courses']);
            return array_merge($course, [
                'instructor' => $instructor,
                'progress' => $isCompleted ? 100 : rand(15, 85),
                'is_completed' => $isCompleted,
            ]);
        }, $enrolledCourses);

        // Mock activity data (weekly heatmap)
        $weeklyActivity = [];
        for ($i = 0; $i < 52; $i++) {
            $weeklyActivity[] = rand(0, 4); // 0-4 activity level
        }

        return $this->render('pages/profile/index.html.twig', [
            'user' => $user,
            'badges' => array_values($userBadges),
            'current_level' => $currentLevel,
            'next_level' => $nextLevel,
            'enrolled_courses' => array_values($enrolledCourses),
            'weekly_activity' => $weeklyActivity,
            'stats' => [
                'courses_completed' => count($user['completed_courses']),
                'total_learning_hours' => 87,
                'certificates_earned' => 2,
                'quizzes_passed' => 12,
            ],
        ]);
    }

    #[Route('/achievements', name: 'app_achievements')]
    public function achievements(): Response
    {
        $user = SampleData::getCurrentUser();
        $badges = SampleData::getBadges();

        // Mark which badges are earned
        $badgesWithStatus = array_map(function($badge) use ($user) {
            $isEarned = in_array($badge['id'], $user['badges']);
            return array_merge($badge, [
                'is_earned' => $isEarned,
                'progress' => $isEarned ? 100 : rand(20, 90),
                'earned_date' => $isEarned ? '2024-01-' . rand(1, 20) : null,
            ]);
        }, $badges);

        return $this->render('pages/profile/achievements.html.twig', [
            'user' => $user,
            'badges' => $badgesWithStatus,
            'earned_count' => count($user['badges']),
            'total_count' => count($badges),
        ]);
    }

    #[Route('/levels', name: 'app_levels')]
    public function levels(): Response
    {
        $user = SampleData::getCurrentUser();
        $levels = SampleData::getLevels();

        // Add status to each level
        $levelsWithStatus = array_map(function($level) use ($user) {
            return array_merge($level, [
                'is_current' => $level['level'] === $user['level'],
                'is_achieved' => $level['level'] <= $user['level'],
            ]);
        }, $levels);

        return $this->render('pages/profile/levels.html.twig', [
            'user' => $user,
            'levels' => $levelsWithStatus,
        ]);
    }

    #[Route('/certificates', name: 'app_certificates')]
    public function certificates(): Response
    {
        $user = SampleData::getCurrentUser();
        $courses = SampleData::getCourses();
        $instructors = SampleData::getInstructors();

        // Get completed courses as certificates
        $completedCourses = array_filter($courses, fn($c) => in_array($c['id'], $user['completed_courses']));
        $certificates = array_map(function($course) use ($instructors) {
            $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
            return [
                'id' => $course['id'],
                'course' => $course,
                'instructor' => $instructor,
                'issued_date' => '2024-01-15',
                'credential_id' => 'SH-' . strtoupper(substr(md5($course['id']), 0, 8)),
            ];
        }, $completedCourses);

        return $this->render('pages/profile/certificates.html.twig', [
            'user' => $user,
            'certificates' => array_values($certificates),
        ]);
    }
}
