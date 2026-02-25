<?php

namespace App\Entity;

use App\Repository\SkilloraKbArticleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SkilloraKbArticleRepository::class)]
#[ORM\Table(name: 'skillora_kb_article')]
class SkilloraKbArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $content;

    // exemple: "compte,paiement,abonnement"
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tags = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    public function getTags(): ?string { return $this->tags; }
    public function setTags(?string $tags): self { $this->tags = $tags; return $this; }
}