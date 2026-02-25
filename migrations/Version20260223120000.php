<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add course_pdf_name column to rendez_vous';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous ADD course_pdf_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous DROP course_pdf_name');
    }
}

