<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Course
{
    public const STATUSES = ["draft", "published", "archived"];

    public const CATEGORIES = [
        "Development",
        "Business",
        "Data Science",
        "Design",
        "Marketing",
        "Personal Development",
        "IT & Software",
        "Photography",
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Title is required.")]
    #[
        Assert\Length(
            min: 3,
            max: 255,
            minMessage: "Title must be at least {{ limit }} characters.",
            maxMessage: "Title cannot be longer than {{ limit }} characters.",
        ),
    ]
    private ?string $title = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Category is required.")]
    #[
        Assert\Choice(
            choices: self::CATEGORIES,
            message: "Choose a valid category.",
        ),
    ]
    private ?string $category = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[
        Assert\Length(
            max: 10000,
            maxMessage: "Description cannot be longer than {{ limit }} characters.",
        ),
    ]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[
        Assert\Length(
            max: 255,
            maxMessage: "Thumbnail cannot be longer than {{ limit }} characters.",
        ),
    ]
    #[
        Assert\Regex(
            pattern: '#^(https?://.+|/[^\\s]+)$#i',
            message: 'Thumbnail must be a valid URL (http/https) or a relative path starting with "/".',
        ),
    ]
    private ?string $thumbnail = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "Status is required.")]
    #[Assert\Choice(choices: self::STATUSES, message: "Choose a valid status.")]
    private ?string $status = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, CourseSection>
     */
    #[
        ORM\OneToMany(
            targetEntity: CourseSection::class,
            mappedBy: "course",
            cascade: ['persist', 'remove'],
            orphanRemoval: true,
        ),
    ]
    #[Assert\Valid]
    private Collection $sections;

    public function __construct()
    {
        $this->sections = new ArrayCollection();
    }

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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    /**
     * @return Collection<int, CourseSection>
     */
    public function getSections(): Collection
    {
        return $this->sections;
    }

    public function addSection(CourseSection $section): static
    {
        if (!$this->sections->contains($section)) {
            $this->sections->add($section);
            $section->setCourse($this);
        }

        return $this;
    }

    public function removeSection(CourseSection $section): static
    {
        if ($this->sections->removeElement($section)) {
            if ($section->getCourse() === $this) {
                $section->setCourse(null);
            }
        }

        return $this;
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
