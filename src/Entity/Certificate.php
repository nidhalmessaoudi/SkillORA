<?php

namespace App\Entity;

use App\Repository\CertificateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CertificateRepository::class)]
#[ORM\Table(name: 'certificate')]
#[ORM\UniqueConstraint(name: 'uniq_certificate_code', columns: ['certificate_code'])]
class Certificate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'certificate')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', unique: true)]
    private ?Enrollment $enrollment = null;

    #[ORM\Column(name: 'certificate_code', length: 64, unique: true)]
    private string $certificateCode;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pdfPath = null;

    #[ORM\Column(length: 255)]
    private string $studentName;

    #[ORM\Column(length: 255)]
    private string $courseTitle;

    public function __construct()
    {
        $this->issuedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnrollment(): ?Enrollment
    {
        return $this->enrollment;
    }

    public function setEnrollment(?Enrollment $enrollment): static
    {
        $this->enrollment = $enrollment;
        return $this;
    }

    public function getCertificateCode(): string
    {
        return $this->certificateCode;
    }

    public function setCertificateCode(string $certificateCode): static
    {
        $this->certificateCode = $certificateCode;
        return $this;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(\DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;
        return $this;
    }

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): static
    {
        $this->pdfPath = $pdfPath;
        return $this;
    }

    public function getStudentName(): string
    {
        return $this->studentName;
    }

    public function setStudentName(string $studentName): static
    {
        $this->studentName = $studentName;
        return $this;
    }

    public function getCourseTitle(): string
    {
        return $this->courseTitle;
    }

    public function setCourseTitle(string $courseTitle): static
    {
        $this->courseTitle = $courseTitle;
        return $this;
    }
}
