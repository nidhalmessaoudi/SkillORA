<?php

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\CourseSection;
use App\Entity\Enrollment;
use App\Entity\Lesson;
use App\Repository\LessonCompletionRepository;
use App\Service\CourseProgressService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CourseProgressServiceTest extends TestCase
{
    private LessonCompletionRepository&MockObject $lessonCompletionRepository;
    private CourseProgressService $service;

    protected function setUp(): void
    {
        $this->lessonCompletionRepository = $this->createMock(LessonCompletionRepository::class);
        $this->service = new CourseProgressService($this->lessonCompletionRepository);
    }

    public function testCalculateProgressReturnsZeroWhenCourseHasNoLessons(): void
    {
        $course = (new Course())
            ->setTitle('Empty Course')
            ->setCategory('Development')
            ->setStatus('draft');

        $enrollment = (new Enrollment())->setCourse($course);

        self::assertSame(0, $this->service->calculateProgress($enrollment));
    }

    public function testCalculateProgressReturnsExpectedPercent(): void
    {
        $course = $this->createCourseWithLessons(4);
        $enrollment = (new Enrollment())->setCourse($course);

        $this->lessonCompletionRepository
            ->expects(self::once())
            ->method('countByEnrollment')
            ->with($enrollment)
            ->willReturn(2);

        self::assertSame(50, $this->service->calculateProgress($enrollment));
    }

    public function testRecalculateEnrollmentProgressMarksCompletedAtHundredPercent(): void
    {
        $course = $this->createCourseWithLessons(3);
        $enrollment = (new Enrollment())->setCourse($course);

        $this->lessonCompletionRepository
            ->method('countByEnrollment')
            ->willReturn(3);

        $progress = $this->service->recalculateEnrollmentProgress($enrollment);

        self::assertSame(100, $progress);
        self::assertSame(100, $enrollment->getProgressPercent());
        self::assertSame(Enrollment::STATUS_COMPLETED, $enrollment->getStatus());
        self::assertNotNull($enrollment->getCompletedAt());
    }

    public function testRecalculateEnrollmentProgressKeepsActiveWhenNotFullyCompleted(): void
    {
        $course = $this->createCourseWithLessons(4);
        $enrollment = (new Enrollment())->setCourse($course);

        $this->lessonCompletionRepository
            ->method('countByEnrollment')
            ->willReturn(1);

        $progress = $this->service->recalculateEnrollmentProgress($enrollment);

        self::assertSame(25, $progress);
        self::assertSame(Enrollment::STATUS_ACTIVE, $enrollment->getStatus());
        self::assertNull($enrollment->getCompletedAt());
    }

    public function testGetNextUncompletedLessonReturnsFirstPendingByOrder(): void
    {
        $course = (new Course())
            ->setTitle('Ordered Course')
            ->setCategory('Development')
            ->setStatus('draft');

        $sectionA = (new CourseSection())->setTitle('Section A')->setPosition(2);
        $sectionB = (new CourseSection())->setTitle('Section B')->setPosition(1);

        $lesson1 = $this->createLesson('Lesson 1', 1, 101);
        $lesson2 = $this->createLesson('Lesson 2', 2, 102);
        $lesson3 = $this->createLesson('Lesson 3', 1, 201);

        $sectionA->addLesson($lesson1);
        $sectionA->addLesson($lesson2);
        $sectionB->addLesson($lesson3);

        $course->addSection($sectionA);
        $course->addSection($sectionB);

        $enrollment = (new Enrollment())->setCourse($course);

        $this->lessonCompletionRepository
            ->expects(self::once())
            ->method('findCompletedLessonIdsForEnrollment')
            ->with($enrollment)
            ->willReturn([201]);

        $next = $this->service->getNextUncompletedLesson($enrollment);

        self::assertSame($lesson1, $next);
    }

    private function createCourseWithLessons(int $lessonCount): Course
    {
        $course = (new Course())
            ->setTitle('Course ' . $lessonCount)
            ->setCategory('Development')
            ->setStatus('draft');

        $section = (new CourseSection())
            ->setTitle('Section 1')
            ->setPosition(1);

        for ($i = 1; $i <= $lessonCount; $i++) {
            $section->addLesson($this->createLesson('Lesson ' . $i, $i, $i));
        }

        $course->addSection($section);

        return $course;
    }

    private function createLesson(string $title, int $position, int $id): Lesson
    {
        $lesson = (new Lesson())
            ->setTitle($title)
            ->setType('text')
            ->setPosition($position)
            ->setContent('content');

        $this->setEntityId($lesson, $id);

        return $lesson;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionObject($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
