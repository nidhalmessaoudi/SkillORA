<?php

namespace App\Controller;

use App\DataFixtures\SampleData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QuizController extends AbstractController
{
    #[Route('/quizzes', name: 'quiz_index')]
    public function index(): Response
    {
        $quizzes = SampleData::getQuizzes();
        $courses = SampleData::getCourses();

        // Add course info to quizzes
        $quizzesWithCourses = array_map(function($quiz) use ($courses) {
            $course = array_values(array_filter($courses, fn($c) => $c['id'] === $quiz['course_id']))[0] ?? null;
            return array_merge($quiz, [
                'course' => $course,
                'attempts' => rand(0, 3),
                'best_score' => rand(0, 3) > 0 ? rand(65, 100) : null,
            ]);
        }, $quizzes);

        return $this->render('pages/quizzes/index.html.twig', [
            'quizzes' => $quizzesWithCourses,
        ]);
    }

    #[Route('/quizzes/{id}', name: 'quiz_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $quizzes = SampleData::getQuizzes();
        $courses = SampleData::getCourses();

        $quiz = array_values(array_filter($quizzes, fn($q) => $q['id'] === $id))[0] ?? null;

        if (!$quiz) {
            throw $this->createNotFoundException('Quiz not found');
        }

        $course = array_values(array_filter($courses, fn($c) => $c['id'] === $quiz['course_id']))[0] ?? null;
        $quiz['course'] = $course;

        // Mock attempt history
        $attempts = [
            ['id' => 1, 'score' => 85, 'passed' => true, 'date' => '2024-01-15', 'time_taken' => '12:34'],
            ['id' => 2, 'score' => 70, 'passed' => true, 'date' => '2024-01-10', 'time_taken' => '14:22'],
        ];

        return $this->render('pages/quizzes/show.html.twig', [
            'quiz' => $quiz,
            'attempts' => $attempts,
        ]);
    }

    #[Route('/quizzes/{id}/attempt', name: 'quiz_attempt', requirements: ['id' => '\d+'])]
    public function attempt(int $id): Response
    {
        $quizzes = SampleData::getQuizzes();
        $courses = SampleData::getCourses();

        $quiz = array_values(array_filter($quizzes, fn($q) => $q['id'] === $id))[0] ?? null;

        if (!$quiz) {
            throw $this->createNotFoundException('Quiz not found');
        }

        $course = array_values(array_filter($courses, fn($c) => $c['id'] === $quiz['course_id']))[0] ?? null;
        $quiz['course'] = $course;

        // Mock questions
        $questions = [
            [
                'id' => 1,
                'type' => 'single',
                'question' => 'What is the time complexity of binary search?',
                'options' => ['O(n)', 'O(log n)', 'O(n²)', 'O(1)'],
                'correct' => 1,
            ],
            [
                'id' => 2,
                'type' => 'single',
                'question' => 'Which data structure uses LIFO principle?',
                'options' => ['Queue', 'Stack', 'Heap', 'Tree'],
                'correct' => 1,
            ],
            [
                'id' => 3,
                'type' => 'multiple',
                'question' => 'Which of the following are valid Python data types? (Select all that apply)',
                'options' => ['int', 'float', 'char', 'bool'],
                'correct' => [0, 1, 3],
            ],
            [
                'id' => 4,
                'type' => 'single',
                'question' => 'What does SQL stand for?',
                'options' => ['Structured Query Language', 'Simple Query Language', 'Standard Query Language', 'Sequential Query Language'],
                'correct' => 0,
            ],
            [
                'id' => 5,
                'type' => 'single',
                'question' => 'Which sorting algorithm has the best average-case time complexity?',
                'options' => ['Bubble Sort', 'Selection Sort', 'Quick Sort', 'Insertion Sort'],
                'correct' => 2,
            ],
        ];

        return $this->render('pages/quizzes/attempt.html.twig', [
            'quiz' => $quiz,
            'questions' => $questions,
            'current_question' => 1,
            'total_questions' => count($questions),
        ]);
    }

    #[Route('/quizzes/{id}/result', name: 'quiz_result', requirements: ['id' => '\d+'])]
    public function results(int $id): Response
    {
        $quizzes = SampleData::getQuizzes();
        $courses = SampleData::getCourses();

        $quiz = array_values(array_filter($quizzes, fn($q) => $q['id'] === $id))[0] ?? null;

        if (!$quiz) {
            throw $this->createNotFoundException('Quiz not found');
        }

        $course = array_values(array_filter($courses, fn($c) => $c['id'] === $quiz['course_id']))[0] ?? null;
        $quiz['course'] = $course;

        // Mock results
        $results = [
            'score' => 80,
            'passed' => true,
            'correct_answers' => 8,
            'total_questions' => 10,
            'time_taken' => '11:45',
            'xp_earned' => 150,
            'breakdown' => [
                ['topic' => 'Data Structures', 'correct' => 3, 'total' => 4],
                ['topic' => 'Algorithms', 'correct' => 3, 'total' => 3],
                ['topic' => 'Programming Basics', 'correct' => 2, 'total' => 3],
            ],
        ];

        return $this->render('pages/quizzes/result.html.twig', [
            'quiz' => $quiz,
            'results' => $results,
        ]);
    }
}
