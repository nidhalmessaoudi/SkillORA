<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refusal_reason column to rendez_vous';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous ADD refusal_reason LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous DROP refusal_reason');
    }
}

