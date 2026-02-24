<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\CourseSection;
use App\Entity\Enrollment;
use App\Entity\Lesson;
use App\Repository\LessonCompletionRepository;

class CourseProgressService
{
    public function __construct(
        private readonly LessonCompletionRepository $lessonCompletionRepository,
    ) {
    }

    public function getTotalLessons(Course $course): int
    {
        $total = 0;
        foreach ($course->getSections() as $section) {
            if ($section instanceof CourseSection) {
                $total += $section->getLessons()->count();
            }
        }

        return $total;
    }

    public function getCompletedLessons(Enrollment $enrollment): int
    {
        return $this->lessonCompletionRepository->countByEnrollment($enrollment);
    }

    public function calculateProgress(Enrollment $enrollment): int
    {
        $totalLessons = $this->getTotalLessons($enrollment->getCourse());
        if ($totalLessons <= 0) {
            return 0;
        }

        $completedLessons = $this->getCompletedLessons($enrollment);
        $percent = (int) floor(($completedLessons / $totalLessons) * 100);

        return max(0, min(100, $percent));
    }

    public function recalculateEnrollmentProgress(Enrollment $enrollment): int
    {
        $progress = $this->calculateProgress($enrollment);
        $enrollment->setProgressPercent($progress);

        if ($progress >= 100 && $this->getTotalLessons($enrollment->getCourse()) > 0) {
            $enrollment->setStatus(Enrollment::STATUS_COMPLETED);
            if ($enrollment->getCompletedAt() === null) {
                $enrollment->setCompletedAt(new \DateTimeImmutable());
            }
        } else {
            $enrollment->setStatus(Enrollment::STATUS_ACTIVE);
        }

        return $progress;
    }

    public function getNextUncompletedLesson(Enrollment $enrollment): ?Lesson
    {
        $course = $enrollment->getCourse();
        $sections = $course->getSections()->toArray();

        usort($sections, static fn (CourseSection $a, CourseSection $b) => ($a->getPosition() ?? 0) <=> ($b->getPosition() ?? 0));

        $completedIds = array_flip($this->lessonCompletionRepository->findCompletedLessonIdsForEnrollment($enrollment));

        foreach ($sections as $section) {
            $lessons = $section->getLessons()->toArray();
            usort($lessons, static fn (Lesson $a, Lesson $b) => ($a->getPosition() ?? 0) <=> ($b->getPosition() ?? 0));

            foreach ($lessons as $lesson) {
                if (!isset($completedIds[$lesson->getId()])) {
                    return $lesson;
                }
            }
        }

        return null;
    }
}
