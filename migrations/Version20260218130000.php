<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Copy slot location onto rendez_vous for in-person meetings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous ADD location_label VARCHAR(255) DEFAULT NULL, ADD location_lat DOUBLE PRECISION DEFAULT NULL, ADD location_lng DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous DROP location_label, DROP location_lat, DROP location_lng');
    }
}

