<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reply')]
class Reply
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // The post this reply belongs to
    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Post $post;

    #[ORM\Column(type: 'text')]
    private string $content;

    // we'll store author as a simple string (static testing user)
    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $authorName = null;

    #[ORM\Column(type: 'integer')]
    private int $upvotes = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getters / setters

    public function getId(): ?int { return $this->id; }

    public function getPost(): Post { return $this->post; }
    public function setPost(Post $post): self { $this->post = $post; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    public function getAuthorName(): ?string { return $this->authorName; }
    public function setAuthorName(?string $name): self { $this->authorName = $name; return $this; }

    public function getUpvotes(): int { return $this->upvotes; }
    public function setUpvotes(int $v): self { $this->upvotes = $v; return $this; }
    public function upvote(): self { $this->upvotes++; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $dt): self { $this->createdAt = $dt; return $this; }

    // inside class Reply
    public function incrementUpvotes(): self
    {
        $this->upvotes = $this->upvotes + 1;
        return $this;
    }

    public function decrementUpvotes(): self
    {
        $this->upvotes = max(0, $this->upvotes - 1);
        return $this;
    }

}
