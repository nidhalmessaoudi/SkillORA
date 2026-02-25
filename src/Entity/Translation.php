<?php
// src/Entity/Translation.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'translation')]
#[ORM\Index(columns: ['content_hash', 'item_type', 'item_id', 'source_lang', 'target_lang'])]
class Translation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $contentHash;

    #[ORM\Column(type: 'string', length: 10)]
    private string $itemType; // 'post' or 'reply'

    #[ORM\Column(type: 'integer')]
    private int $itemId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $sourceLang;

    #[ORM\Column(type: 'string', length: 20)]
    private string $targetLang;

    #[ORM\Column(type: 'text')]
    private string $originalText;

    #[ORM\Column(type: 'text')]
    private string $translatedText;

    #[ORM\Column(type: 'string', length: 50)]
    private string $provider = 'huggingface_nllb';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function setContentHash(string $contentHash): self
    {
        $this->contentHash = $contentHash;
        return $this;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): self
    {
        $this->itemType = $itemType;
        return $this;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function setItemId(int $itemId): self
    {
        $this->itemId = $itemId;
        return $this;
    }

    public function getSourceLang(): string
    {
        return $this->sourceLang;
    }

    public function setSourceLang(string $sourceLang): self
    {
        $this->sourceLang = $sourceLang;
        return $this;
    }

    public function getTargetLang(): string
    {
        return $this->targetLang;
    }

    public function setTargetLang(string $targetLang): self
    {
        $this->targetLang = $targetLang;
        return $this;
    }

    public function getOriginalText(): string
    {
        return $this->originalText;
    }

    public function setOriginalText(string $originalText): self
    {
        $this->originalText = $originalText;
        return $this;
    }

    public function getTranslatedText(): string
    {
        return $this->translatedText;
    }

    public function setTranslatedText(string $translatedText): self
    {
        $this->translatedText = $translatedText;
        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}