<?php

namespace App\Service;

use App\Entity\Course;

class CourseValidationService
{
    public function validate(Course $course): bool
    {
        $title = trim((string) $course->getTitle());
        if ($title === '') {
            throw new \InvalidArgumentException('Course title is required.');
        }

        if (mb_strlen($title) < 3) {
            throw new \InvalidArgumentException('Course title must be at least 3 characters.');
        }

        $category = (string) $course->getCategory();
        if (!in_array($category, Course::CATEGORIES, true)) {
            throw new \InvalidArgumentException('Course category is invalid.');
        }

        $status = (string) $course->getStatus();
        if (!in_array($status, Course::STATUSES, true)) {
            throw new \InvalidArgumentException('Course status is invalid.');
        }

        if ($status === 'published' && $course->getSections()->count() === 0) {
            throw new \InvalidArgumentException('Published course must contain at least one section.');
        }

        return true;
    }
}
