<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reaction')]
#[ORM\UniqueConstraint(name: 'uniq_user_post', columns: ['user_id', 'post_id'])]
#[ORM\UniqueConstraint(name: 'uniq_user_reply', columns: ['user_id', 'reply_id'])]
class Reaction
{
    public const TYPE_LIKE = 'like';
    public const TYPE_LOVE = 'love';
    public const TYPE_HAHA = 'haha';
    public const TYPE_WOW = 'wow';
    public const TYPE_SAD = 'sad';
    public const TYPE_ANGRY = 'angry';

    public const AVAILABLE_TYPES = [
        self::TYPE_LIKE,
        self::TYPE_LOVE,
        self::TYPE_HAHA,
        self::TYPE_WOW,
        self::TYPE_SAD,
        self::TYPE_ANGRY,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: Reply::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Reply $reply = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, self::AVAILABLE_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid reaction type: ' . $type);
        }

        $this->type = $type;
        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): self
    {
        $this->post = $post;
        return $this;
    }

    public function getReply(): ?Reply
    {
        return $this->reply;
    }

    public function setReply(?Reply $reply): self
    {
        $this->reply = $reply;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}