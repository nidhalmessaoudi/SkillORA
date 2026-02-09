<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209154128 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, category VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, thumbnail VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE course_section (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, position INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, course_id INT NOT NULL, INDEX IDX_25B07F03591CC992 (course_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, event_type VARCHAR(50) NOT NULL, price_type VARCHAR(50) NOT NULL, image VARCHAR(255) DEFAULT NULL, salle_id INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lesson (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, content LONGTEXT DEFAULT NULL, file_path VARCHAR(255) DEFAULT NULL, position INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, section_id INT NOT NULL, INDEX IDX_F87474F3D823E37A (section_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, salle_id INT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, telephone VARCHAR(20) NOT NULL, adresse VARCHAR(255) DEFAULT NULL, nombre_places INT NOT NULL, date_reservation DATETIME NOT NULL, user_id INT UNSIGNED DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE salle (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, image_3d VARCHAR(255) DEFAULT NULL, max_participants INT NOT NULL, duration INT NOT NULL, equipment LONGTEXT DEFAULT NULL, location VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, username VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, is_active TINYINT DEFAULT 1, is_verified TINYINT DEFAULT 0, last_login_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, phone VARCHAR(20) DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, date_of_birth DATE DEFAULT NULL, field_of_study VARCHAR(100) DEFAULT NULL, university VARCHAR(100) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, profile_completed TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE course_section ADD CONSTRAINT FK_25B07F03591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('ALTER TABLE lesson ADD CONSTRAINT FK_F87474F3D823E37A FOREIGN KEY (section_id) REFERENCES course_section (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course_section DROP FOREIGN KEY FK_25B07F03591CC992');
        $this->addSql('ALTER TABLE lesson DROP FOREIGN KEY FK_F87474F3D823E37A');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE course_section');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE lesson');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE salle');
        $this->addSql('DROP TABLE users');
    }
}
