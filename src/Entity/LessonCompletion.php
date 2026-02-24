<?php

namespace App\Entity;

use App\Repository\LessonCompletionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LessonCompletionRepository::class)]
#[ORM\Table(name: 'lesson_completion')]
#[ORM\UniqueConstraint(name: 'uniq_enrollment_lesson', columns: ['enrollment_id', 'lesson_id'])]
class LessonCompletion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lessonCompletions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Enrollment $enrollment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Lesson $lesson = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $completedAt;

    public function __construct()
    {
        $this->completedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnrollment(): ?Enrollment
    {
        return $this->enrollment;
    }

    public function setEnrollment(?Enrollment $enrollment): static
    {
        $this->enrollment = $enrollment;
        return $this;
    }

    public function getLesson(): ?Lesson
    {
        return $this->lesson;
    }

    public function setLesson(?Lesson $lesson): static
    {
        $this->lesson = $lesson;
        return $this;
    }

    public function getCompletedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }
}
