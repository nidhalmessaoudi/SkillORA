<?php

namespace App\Entity;

use App\Repository\EnrollmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnrollmentRepository::class)]
#[ORM\Table(name: 'enrollment')]
#[ORM\UniqueConstraint(name: 'uniq_enrollment_user_course', columns: ['user_id', 'course_id'])]
class Enrollment
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', options: ['unsigned' => true])]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Course $course = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $enrolledAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $progressPercent = 0;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_ACTIVE])]
    private string $status = self::STATUS_ACTIVE;

    /**
     * @var Collection<int, LessonCompletion>
     */
    #[ORM\OneToMany(mappedBy: 'enrollment', targetEntity: LessonCompletion::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $lessonCompletions;

    #[ORM\OneToOne(mappedBy: 'enrollment', targetEntity: Certificate::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private ?Certificate $certificate = null;

    public function __construct()
    {
        $this->enrolledAt = new \DateTimeImmutable();
        $this->lessonCompletions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;
        return $this;
    }

    public function getEnrolledAt(): \DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    public function setEnrolledAt(\DateTimeImmutable $enrolledAt): static
    {
        $this->enrolledAt = $enrolledAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getProgressPercent(): int
    {
        return $this->progressPercent;
    }

    public function setProgressPercent(int $progressPercent): static
    {
        $this->progressPercent = max(0, min(100, $progressPercent));
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection<int, LessonCompletion>
     */
    public function getLessonCompletions(): Collection
    {
        return $this->lessonCompletions;
    }

    public function addLessonCompletion(LessonCompletion $lessonCompletion): static
    {
        if (!$this->lessonCompletions->contains($lessonCompletion)) {
            $this->lessonCompletions->add($lessonCompletion);
            $lessonCompletion->setEnrollment($this);
        }

        return $this;
    }

    public function removeLessonCompletion(LessonCompletion $lessonCompletion): static
    {
        if ($this->lessonCompletions->removeElement($lessonCompletion)) {
            if ($lessonCompletion->getEnrollment() === $this) {
                $lessonCompletion->setEnrollment(null);
            }
        }

        return $this;
    }

    public function getCertificate(): ?Certificate
    {
        return $this->certificate;
    }

    public function setCertificate(?Certificate $certificate): static
    {
        if ($certificate !== null && $certificate->getEnrollment() !== $this) {
            $certificate->setEnrollment($this);
        }

        $this->certificate = $certificate;
        return $this;
    }
}
