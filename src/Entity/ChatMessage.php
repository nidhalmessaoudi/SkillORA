<?php

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: 'chat_message')]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ChatSession::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ChatSession $session = null;

    // 'user' | 'assistant'
    #[ORM\Column(length: 16)]
    private string $role;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    // Pour analytics (optionnel)
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $inputTokens = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $outputTokens = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $totalTokens = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $meta = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSession(): ?ChatSession { return $this->session; }
    public function setSession(ChatSession $session): self { $this->session = $session; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $role): self { $this->role = $role; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setUsage(?int $in, ?int $out, ?int $total): self
    {
        $this->inputTokens = $in;
        $this->outputTokens = $out;
        $this->totalTokens = $total;
        return $this;
    }

    public function setMeta(?array $meta): self { $this->meta = $meta; return $this; }
}