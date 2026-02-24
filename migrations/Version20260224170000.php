<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add enrollment, lesson_completion and certificate tables for course progress and certificates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE enrollment (id INT AUTO_INCREMENT NOT NULL, user_id INT UNSIGNED NOT NULL, course_id INT NOT NULL, enrolled_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", completed_at DATETIME DEFAULT NULL COMMENT "(DC2Type:datetime_immutable)", progress_percent SMALLINT DEFAULT 0 NOT NULL, status VARCHAR(20) DEFAULT "active" NOT NULL, INDEX IDX_E9385A9A76ED395 (user_id), INDEX IDX_E9385A9591CC992 (course_id), UNIQUE INDEX uniq_enrollment_user_course (user_id, course_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lesson_completion (id INT AUTO_INCREMENT NOT NULL, enrollment_id INT NOT NULL, lesson_id INT NOT NULL, completed_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_429A6658DA8F9FD (enrollment_id), INDEX IDX_429A6658CDF80196 (lesson_id), UNIQUE INDEX uniq_enrollment_lesson (enrollment_id, lesson_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE certificate (id INT AUTO_INCREMENT NOT NULL, enrollment_id INT NOT NULL, certificate_code VARCHAR(64) NOT NULL, issued_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", pdf_path VARCHAR(255) DEFAULT NULL, student_name VARCHAR(255) NOT NULL, course_title VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_379D4E9482C2670A (enrollment_id), UNIQUE INDEX uniq_certificate_code (certificate_code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_E9385A9A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_E9385A9591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lesson_completion ADD CONSTRAINT FK_429A6658DA8F9FD FOREIGN KEY (enrollment_id) REFERENCES enrollment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lesson_completion ADD CONSTRAINT FK_429A6658CDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE certificate ADD CONSTRAINT FK_379D4E9482C2670A FOREIGN KEY (enrollment_id) REFERENCES enrollment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE certificate DROP FOREIGN KEY FK_379D4E9482C2670A');
        $this->addSql('ALTER TABLE lesson_completion DROP FOREIGN KEY FK_429A6658DA8F9FD');
        $this->addSql('ALTER TABLE lesson_completion DROP FOREIGN KEY FK_429A6658CDF80196');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_E9385A9A76ED395');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_E9385A9591CC992');

        $this->addSql('DROP TABLE certificate');
        $this->addSql('DROP TABLE lesson_completion');
        $this->addSql('DROP TABLE enrollment');
    }
}
