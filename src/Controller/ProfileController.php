<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'profile_index')]
    public function index(): Response
    {
        $user = SampleData::getCurrentUser();
        $courses = SampleData::getCourses();
        $instructors = SampleData::getInstructors();
        $badges = SampleData::getBadges();
        $levels = SampleData::getLevels();

        // Get user's enrolled courses with details
        $enrolledCourses = array_filter($courses, fn($c) => in_array($c['id'], $user['enrolled_courses']));
        $enrolledCourses = array_map(function($course) use ($instructors, $user) {
            $instructor = array_values(array_filter($instructors, fn($i) => $i['id'] === $course['instructor_id']))[0] ?? null;
            $isCompleted = in_array($course['id'], $user['completed_courses']);
            return array_merge($course, [
                'instructor' => $instructor,
                'progress' => $isCompleted ? 100 : rand(30, 85),
                'is_completed' => $isCompleted,
            ]);
        }, $enrolledCourses);

        // Get user's badges
        $userBadges = array_filter($badges, fn($b) => in_array($b['id'], $user['badges']));
        $userBadges = array_map(function($badge) {
            $colors = [
                'harbor' => 'from-harbor-500 to-harbor-600',
                'warning' => 'from-amber-500 to-amber-600',
                'coral' => 'from-coral-400 to-coral-500',
                'success' => 'from-emerald-500 to-emerald-600',
                'danger' => 'from-red-500 to-red-600',
            ];
            return array_merge($badge, [
                'color' => $colors[$badge['color']] ?? 'from-harbor-500 to-harbor-600',
                'earned_at' => rand(1, 30) . ' days ago',
            ]);
        }, $userBadges);

        // Get current level info
        $currentLevel = array_values(array_filter($levels, fn($l) => $l['level'] === $user['level']))[0] ?? $levels[0];
        $nextLevel = array_values(array_filter($levels, fn($l) => $l['level'] === $user['level'] + 1))[0] ?? null;

        // User stats
        $stats = [
            'courses_completed' => count($user['completed_courses']),
            'courses_in_progress' => count($user['enrolled_courses']) - count($user['completed_courses']),
            'total_learning_hours' => 127,
            'certificates' => count($user['completed_courses']),
        ];

        return $this->render('pages/profile/index.html.twig', [
            'user' => $user,
            'enrolledCourses' => array_values($enrolledCourses),
            'badges' => array_values($userBadges),
            'currentLevel' => $currentLevel,
            'nextLevel' => $nextLevel,
            'levels' => $levels,
            'stats' => $stats,
        ]);
    }

    #[Route('/profile/achievements', name: 'profile_achievements')]
    public function achievements(): Response
    {
        $user = SampleData::getCurrentUser();
        $allBadges = SampleData::getBadges();
        $levels = SampleData::getLevels();

        // Format badges with earned status
        $badges = array_map(function($badge) use ($user) {
            $isEarned = in_array($badge['id'], $user['badges']);
            $colors = [
                'harbor' => 'from-harbor-500 to-harbor-600',
                'warning' => 'from-amber-500 to-amber-600',
                'coral' => 'from-coral-400 to-coral-500',
                'success' => 'from-emerald-500 to-emerald-600',
                'danger' => 'from-red-500 to-red-600',
            ];
            return array_merge($badge, [
                'is_earned' => $isEarned,
                'color' => $colors[$badge['color']] ?? 'from-harbor-500 to-harbor-600',
                'earned_at' => $isEarned ? rand(1, 30) . ' days ago' : null,
            ]);
        }, $allBadges);

        // Separate earned and locked badges
        $earnedBadges = array_filter($badges, fn($b) => $b['is_earned']);
        $lockedBadges = array_filter($badges, fn($b) => !$b['is_earned']);

        return $this->render('pages/profile/achievements.html.twig', [
            'user' => $user,
            'earnedBadges' => array_values($earnedBadges),
            'lockedBadges' => array_values($lockedBadges),
            'levels' => $levels,
            'totalXp' => $user['xp'],
        ]);
    }

    #[Route('/leaderboard', name: 'profile_leaderboard')]
    public function leaderboard(): Response
    {
        $user = SampleData::getCurrentUser();
        $levels = SampleData::getLevels();

        // Mock leaderboard data
        $leaderboard = [
            ['rank' => 1, 'name' => 'Sarah Chen', 'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop', 'xp' => 45200, 'level' => 7, 'level_name' => 'Grandmaster', 'streak' => 45],
            ['rank' => 2, 'name' => 'Marcus Johnson', 'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop', 'xp' => 38900, 'level' => 7, 'level_name' => 'Grandmaster', 'streak' => 32],
            ['rank' => 3, 'name' => 'Elena Rodriguez', 'avatar' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&h=100&fit=crop', 'xp' => 32100, 'level' => 6, 'level_name' => 'Master', 'streak' => 28],
            ['rank' => 4, 'name' => 'James Mitchell', 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop', 'xp' => 28400, 'level' => 6, 'level_name' => 'Master', 'streak' => 21],
            ['rank' => 5, 'name' => 'Aisha Patel', 'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop', 'xp' => 24800, 'level' => 6, 'level_name' => 'Master', 'streak' => 19],
            ['rank' => 6, 'name' => 'David Kim', 'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop', 'xp' => 21500, 'level' => 6, 'level_name' => 'Master', 'streak' => 15],
            ['rank' => 7, 'name' => 'Lisa Thompson', 'avatar' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=100&h=100&fit=crop', 'xp' => 18200, 'level' => 5, 'level_name' => 'Expert', 'streak' => 14],
            ['rank' => 8, 'name' => 'Robert Chen', 'avatar' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=100&h=100&fit=crop', 'xp' => 15900, 'level' => 5, 'level_name' => 'Expert', 'streak' => 11],
            ['rank' => 9, 'name' => 'Jennifer Walsh', 'avatar' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?w=100&h=100&fit=crop', 'xp' => 12400, 'level' => 5, 'level_name' => 'Expert', 'streak' => 9],
            ['rank' => 10, 'name' => $user['name'], 'avatar' => $user['avatar'], 'xp' => $user['xp'], 'level' => $user['level'], 'level_name' => $user['level_name'], 'streak' => $user['streak'], 'is_current_user' => true],
        ];

        // Sort by XP
        usort($leaderboard, fn($a, $b) => $b['xp'] <=> $a['xp']);

        // Update ranks
        foreach ($leaderboard as $i => &$entry) {
            $entry['rank'] = $i + 1;
        }

        return $this->render('pages/profile/leaderboard.html.twig', [
            'user' => $user,
            'leaderboard' => $leaderboard,
            'levels' => $levels,
        ]);
    }
}
