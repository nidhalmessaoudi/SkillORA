<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add location fields to availability slots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE availability_slots ADD location_label VARCHAR(255) DEFAULT NULL, ADD location_lat DOUBLE PRECISION DEFAULT NULL, ADD location_lng DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE availability_slots DROP location_label, DROP location_lat, DROP location_lng');
    }
}

