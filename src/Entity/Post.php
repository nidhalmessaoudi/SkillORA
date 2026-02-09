<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'post')]
#[ORM\HasLifecycleCallbacks]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $topic = null;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'integer')]
    private int $upvotes = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'posts', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'post_tag')]
    private Collection $tags;

    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Reply::class, cascade: ['persist','remove'], orphanRemoval: true)]
    private Collection $replies;


    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->replies = new ArrayCollection(); 
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime(); // <- changed to DateTime
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime(); // <- changed to DateTime
    }

    // ---------------- Getters / Setters ----------------

    public function getId(): ?int { return $this->id; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getTopic(): ?string { return $this->topic; }
    public function setTopic(?string $topic): self { $this->topic = $topic; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    public function getUpvotes(): int { return $this->upvotes; }
    public function setUpvotes(int $upvotes): self { $this->upvotes = $upvotes; return $this; }
    public function upvote(): self { $this->upvotes++; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }

    /** @return Collection|Tag[] */
    public function getTags(): Collection { return $this->tags; }
    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags[] = $tag;
        }
        return $this;
    }
    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);
        return $this;
    }

     /** @return Collection|Reply[] */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(Reply $reply): self
    {
        if (!$this->replies->contains($reply)) {
            $this->replies[] = $reply;
            $reply->setPost($this);
        }
        return $this;
    }

    public function removeReply(Reply $reply): self
    {
        if ($this->replies->contains($reply)) {
            $this->replies->removeElement($reply);
            // if needed, unset owning side
            // if ($reply->getPost() === $this) $reply->setPost(null);
        }
        return $this;
    }

    public function getReplyCount(): int
    {
        // $this->replies is a Collection
        return $this->replies->count();
    }

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
