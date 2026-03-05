<?php

namespace App\Service;

use App\Entity\RendezVous;
use InvalidArgumentException;

final class RendezVousValidator
{
    public function validate(RendezVous $rendezVous): bool
    {
        $meetingType = $rendezVous->getMeetingType();

        if (!in_array($meetingType, [RendezVous::TYPE_ONLINE, RendezVous::TYPE_IN_PERSON], true)) {
            throw new InvalidArgumentException(sprintf(
                'Type de rendez-vous invalide: "%s".',
                $meetingType
            ));
        }

        if ($meetingType === RendezVous::TYPE_ONLINE) {
            $meetingLink = trim((string) $rendezVous->getMeetingLink());

            if ($meetingLink === '') {
                throw new InvalidArgumentException('Le lien de réunion est obligatoire pour un rendez-vous en ligne.');
            }

            if (filter_var($meetingLink, FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException('Le lien de réunion doit être une URL valide.');
            }
        }

        if ($meetingType === RendezVous::TYPE_IN_PERSON) {
            $locationLabel = trim((string) $rendezVous->getLocationLabel());

            if ($locationLabel === '') {
                throw new InvalidArgumentException('Le lieu est obligatoire pour un rendez-vous en personne.');
            }
        }

        return true;
    }
}
