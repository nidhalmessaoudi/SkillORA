<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'salle')]
class Salle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'image_3d', type: 'string', length: 255, nullable: true)]
    private ?string $image3d = null;

    #[ORM\Column(name: 'max_participants', type: 'integer')]
    private ?int $maxParticipants = null;

    #[ORM\Column(type: 'integer')]
    private ?int $duration = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $equipment = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $location = null;

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getImage3d(): ?string
    {
        return $this->image3d;
    }

    public function setImage3d(?string $image3d): static
    {
        $this->image3d = $image3d;
        return $this;
    }

    public function getMaxParticipants(): ?int
    {
        return $this->maxParticipants;
    }

    public function setMaxParticipants(int $maxParticipants): static
    {
        $this->maxParticipants = $maxParticipants;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getEquipment(): ?string
    {
        return $this->equipment;
    }

    public function setEquipment(?string $equipment): static
    {
        $this->equipment = $equipment;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;
        return $this;
    }
}
