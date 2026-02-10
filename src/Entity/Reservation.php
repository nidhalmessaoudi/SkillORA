<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'event_id', type: 'integer')]
    private ?int $eventId = null;

    #[ORM\Column(name: 'salle_id', type: 'integer')]
    private ?int $salleId = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(name: 'nombre_places', type: 'string', length: 255)]
    private ?string $nombrePlaces = null;

    #[ORM\Column(name: 'date_reservation', type: 'datetime')]
    private ?\DateTimeInterface $dateReservation = null;

    #[ORM\Column(name: 'user_id', type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $userId = null;

    public function __construct()
    {
        $this->dateReservation = new \DateTime();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventId(): ?int
    {
        return $this->eventId;
    }

    public function setEventId(int $eventId): static
    {
        $this->eventId = $eventId;
        return $this;
    }

    public function getSalleId(): ?int
    {
        return $this->salleId;
    }

    public function setSalleId(int $salleId): static
    {
        $this->salleId = $salleId;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getNombrePlaces(): ?string
    {
        return $this->nombrePlaces;
    }

    public function setNombrePlaces(string $nombrePlaces): static
    {
        $this->nombrePlaces = $nombrePlaces;
        return $this;
    }

    public function getDateReservation(): ?\DateTimeInterface
    {
        return $this->dateReservation;
    }

    public function setDateReservation(\DateTimeInterface $dateReservation): static
    {
        $this->dateReservation = $dateReservation;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;
        return $this;
    }
}
