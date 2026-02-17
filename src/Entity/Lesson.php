<?php

namespace App\Entity;

use App\Repository\LessonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: LessonRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Assert\Callback("validateLesson")]
class Lesson
{
    public const TYPES = ["text", "pdf", "video"];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Lesson title is required.")]
    #[
        Assert\Length(
            min: 2,
            max: 255,
            minMessage: "Lesson title must be at least {{ limit }} characters.",
            maxMessage: "Lesson title cannot be longer than {{ limit }} characters.",
        ),
    ]
    private ?string $title = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Lesson type is required.")]
    #[
        Assert\Choice(
            choices: self::TYPES,
            message: "Choose a valid lesson type.",
        ),
    ]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[
        Assert\Length(
            max: 50000,
            maxMessage: "Content cannot be longer than {{ limit }} characters.",
        ),
    ]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[
        Assert\Length(
            max: 255,
            maxMessage: "File path cannot be longer than {{ limit }} characters.",
        ),
    ]
    private ?string $filePath = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "Position is required.")]
    #[Assert\Type(type: "integer", message: "Position must be an integer.")]
    #[
        Assert\GreaterThanOrEqual(
            value: 1,
            message: "Position must be at least {{ compared_value }}.",
        ),
    ]
    private ?int $position = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: "lessons")]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Section is required.")]
    private ?CourseSection $section = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getSection(): ?CourseSection
    {
        return $this->section;
    }

    public function setSection(?CourseSection $section): static
    {
        $this->section = $section;
        return $this;
    }

    public function validateLesson(ExecutionContextInterface $context): void
    {
        $type = (string) ($this->type ?? "");
        $content = trim((string) ($this->content ?? ""));

        if ($type === "text") {
            if ($content === "") {
                $context
                    ->buildViolation("Content is required for Text lessons.")
                    ->atPath("content")
                    ->addViolation();
            }
        }
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
