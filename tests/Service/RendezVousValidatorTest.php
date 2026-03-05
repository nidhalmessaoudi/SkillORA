<?php

namespace App\Tests\Service;

use App\Entity\RendezVous;
use App\Service\RendezVousValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RendezVousValidatorTest extends TestCase
{
    public function testValidOnlineRendezVousReturnsTrue(): void
    {
        $rendezVous = (new RendezVous())
            ->setMeetingType(RendezVous::TYPE_ONLINE)
            ->setMeetingLink('https://meet.example.com/session-123');

        $validator = new RendezVousValidator();

        self::assertTrue($validator->validate($rendezVous));
    }

    public function testOnlineRendezVousWithoutMeetingLinkThrowsException(): void
    {
        $rendezVous = (new RendezVous())
            ->setMeetingType(RendezVous::TYPE_ONLINE)
            ->setMeetingLink(null);

        $validator = new RendezVousValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le lien de réunion est obligatoire pour un rendez-vous en ligne.');

        $validator->validate($rendezVous);
    }

    public function testRendezVousWithInvalidMeetingTypeThrowsException(): void
    {
        $rendezVous = (new RendezVous())
            ->setMeetingType('type_inconnu')
            ->setMeetingLink('https://meet.example.com/session-123');

        $validator = new RendezVousValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Type de rendez-vous invalide');

        $validator->validate($rendezVous);
    }

    public function testInPersonRendezVousWithoutLocationThrowsException(): void
    {
        $rendezVous = (new RendezVous())
            ->setMeetingType(RendezVous::TYPE_IN_PERSON)
            ->setLocationLabel(null);

        $validator = new RendezVousValidator();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le lieu est obligatoire pour un rendez-vous en personne.');

        $validator->validate($rendezVous);
    }
}
