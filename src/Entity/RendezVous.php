<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rendez_vous')]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $debut = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $fin = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 20)]
    private ?string $statut = 'PENDING';

    #[ORM\Column(name: 'lien_meeting', type: 'string', length: 255, nullable: true)]
    private ?string $lienMeeting = null;

    #[ORM\Column(name: 'cree_le', type: 'datetime')]
    private ?\DateTimeInterface $creeLe = null;

    #[ORM\Column(name: 'maj_le', type: 'datetime')]
    private ?\DateTimeInterface $majLe = null;

    public function __construct()
    {
        $this->creeLe = new \DateTime();
        $this->majLe = new \DateTime();
        $this->statut = 'PENDING';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
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

    public function getDebut(): ?\DateTimeInterface
    {
        return $this->debut;
    }

    public function setDebut(\DateTimeInterface $debut): static
    {
        $this->debut = $debut;
        return $this;
    }

    public function getFin(): ?\DateTimeInterface
    {
        return $this->fin;
    }

    public function setFin(\DateTimeInterface $fin): static
    {
        $this->fin = $fin;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getLienMeeting(): ?string
    {
        return $this->lienMeeting;
    }

    public function setLienMeeting(?string $lienMeeting): static
    {
        $this->lienMeeting = $lienMeeting;
        return $this;
    }

    public function getCreeLe(): ?\DateTimeInterface
    {
        return $this->creeLe;
    }

    public function setCreeLe(\DateTimeInterface $creeLe): static
    {
        $this->creeLe = $creeLe;
        return $this;
    }

    public function getMajLe(): ?\DateTimeInterface
    {
        return $this->majLe;
    }

    public function setMajLe(\DateTimeInterface $majLe): static
    {
        $this->majLe = $majLe;
        return $this;
    }
}
