<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reply')]
class Reply
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Post $post;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    // NEW: Self-referencing for nested replies
    #[ORM\ManyToOne(targetEntity: Reply::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'CASCADE')]
    private ?Reply $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: Reply::class, cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $replies;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->replies = new ArrayCollection();
    }

    // Getters / setters

    public function getId(): ?int { return $this->id; }

    public function getPost(): Post { return $this->post; }
    public function setPost(Post $post): self { $this->post = $post; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    public function getAuthor(): User { return $this->author; }
    public function setAuthor(User $author): self { $this->author = $author; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $dt): self { $this->createdAt = $dt; return $this; }

    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTime $dt): self { $this->updatedAt = $dt; return $this; }

    // NEW: Parent reply methods
    public function getParent(): ?Reply { return $this->parent; }
    public function setParent(?Reply $parent): self { $this->parent = $parent; return $this; }

    // NEW: Child replies methods
    /**
     * @return Collection<int, Reply>
     */
    public function getReplies(): Collection { return $this->replies; }

    public function addReply(Reply $reply): self
    {
        if (!$this->replies->contains($reply)) {
            $this->replies[] = $reply;
            $reply->setParent($this);
        }
        return $this;
    }

    public function removeReply(Reply $reply): self
    {
        if ($this->replies->removeElement($reply)) {
            if ($reply->getParent() === $this) {
                $reply->setParent(null);
            }
        }
        return $this;
    }

    // Helper: Check if this is a top-level reply
    public function isTopLevel(): bool
    {
        return $this->parent === null;
    }

    // Helper: Get nesting level (0 = top level, 1 = first nested, etc.)
    public function getDepth(): int
    {
        $depth = 0;
        $current = $this->parent;
        while ($current !== null) {
            $depth++;
            $current = $current->getParent();
        }
        return $depth;
    }
}