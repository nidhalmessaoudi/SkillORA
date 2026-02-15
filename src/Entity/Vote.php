<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vote')]
#[ORM\UniqueConstraint(name: 'uniq_user_post', columns: ['user_identifier', 'post_id'])]
#[ORM\UniqueConstraint(name: 'uniq_user_reply', columns: ['user_identifier', 'reply_id'])]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'user_identifier', type: 'string', length: 191)]
    private string $userIdentifier;

    #[ORM\Column(type: 'smallint')]
    private int $value; // +1 or -1

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: null)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: Reply::class, inversedBy: null)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Reply $reply = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): self
    {
        $this->userIdentifier = $userIdentifier;
        return $this;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): self
    {
        if (!in_array($value, [1, -1], true)) {
            throw new \InvalidArgumentException('Vote value must be 1 or -1');
        }

        $this->value = $value;
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
}
