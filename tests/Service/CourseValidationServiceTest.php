<?php

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\CourseSection;
use App\Service\CourseValidationService;
use PHPUnit\Framework\TestCase;

class CourseValidationServiceTest extends TestCase
{
    private CourseValidationService $service;

    protected function setUp(): void
    {
        $this->service = new CourseValidationService();
    }

    public function testValidCourseReturnsTrue(): void
    {
        $course = (new Course())
            ->setTitle('Symfony Fundamentals')
            ->setCategory('Development')
            ->setStatus('draft');

        self::assertTrue($this->service->validate($course));
    }

    public function testCourseWithoutTitleThrowsException(): void
    {
        $course = (new Course())
            ->setCategory('Development')
            ->setStatus('draft');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Course title is required.');

        $this->service->validate($course);
    }

    public function testCourseWithInvalidCategoryThrowsException(): void
    {
        $course = (new Course())
            ->setTitle('Data Course')
            ->setCategory('Unknown Category')
            ->setStatus('draft');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Course category is invalid.');

        $this->service->validate($course);
    }

    public function testCourseWithInvalidStatusThrowsException(): void
    {
        $course = (new Course())
            ->setTitle('Data Course')
            ->setCategory('Data Science')
            ->setStatus('invalid_status');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Course status is invalid.');

        $this->service->validate($course);
    }

    public function testPublishedCourseWithoutSectionsThrowsException(): void
    {
        $course = (new Course())
            ->setTitle('Published Course')
            ->setCategory('Business')
            ->setStatus('published');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Published course must contain at least one section.');

        $this->service->validate($course);
    }

    public function testPublishedCourseWithSectionReturnsTrue(): void
    {
        $course = (new Course())
            ->setTitle('Published Course')
            ->setCategory('Business')
            ->setStatus('published');

        $section = (new CourseSection())
            ->setTitle('Getting Started')
            ->setPosition(1);

        $course->addSection($section);

        self::assertTrue($this->service->validate($course));
    }
}
