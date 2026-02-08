<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207140239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_badges DROP FOREIGN KEY `user_badges_ibfk_1`');
        $this->addSql('ALTER TABLE user_badges DROP FOREIGN KEY `user_badges_ibfk_2`');
        $this->addSql('ALTER TABLE user_roles DROP FOREIGN KEY `user_roles_ibfk_1`');
        $this->addSql('ALTER TABLE user_settings DROP FOREIGN KEY `user_settings_ibfk_1`');
        $this->addSql('ALTER TABLE user_social_links DROP FOREIGN KEY `user_social_links_ibfk_1`');
        $this->addSql('DROP TABLE badges');
        $this->addSql('DROP TABLE user_badges');
        $this->addSql('DROP TABLE user_roles');
        $this->addSql('DROP TABLE user_settings');
        $this->addSql('DROP TABLE user_social_links');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY `event_ibfk_1`');
        $this->addSql('DROP INDEX salle_id ON event');
        $this->addSql('ALTER TABLE event CHANGE description description LONGTEXT DEFAULT NULL, CHANGE image image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY `fk_reservation_user`');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY `reservation_ibfk_1`');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY `reservation_ibfk_2`');
        $this->addSql('DROP INDEX event_id ON reservation');
        $this->addSql('DROP INDEX salle_id ON reservation');
        $this->addSql('DROP INDEX fk_reservation_user ON reservation');
        $this->addSql('ALTER TABLE reservation DROP user, CHANGE adresse adresse VARCHAR(255) DEFAULT NULL, CHANGE date_reservation date_reservation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE salle ADD event_id INT DEFAULT NULL, CHANGE image_3d image_3d VARCHAR(255) DEFAULT NULL, CHANGE equipment equipment LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_xp ON users');
        $this->addSql('DROP INDEX idx_email ON users');
        $this->addSql('DROP INDEX idx_streak ON users');
        $this->addSql('DROP INDEX idx_username ON users');
        $this->addSql('DROP INDEX idx_level ON users');
        $this->addSql('ALTER TABLE users DROP avatar, DROP bio, DROP location, DROP website, DROP email_verified_at, DROP remember_token, DROP xp, DROP level, DROP level_name, DROP streak, DROP current_streak_start, DROP updated_at, CHANGE first_name first_name VARCHAR(100) DEFAULT NULL, CHANGE last_name last_name VARCHAR(100) DEFAULT NULL, CHANGE last_login_at last_login_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE users RENAME INDEX email TO UNIQ_1483A5E9E7927C74');
        $this->addSql('ALTER TABLE users RENAME INDEX username TO UNIQ_1483A5E9F85E0677');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE badges (id INT UNSIGNED AUTO_INCREMENT NOT NULL, name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, icon VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, color VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'\'\'primary\'\'\' COLLATE `utf8mb4_unicode_ci`, xp_required INT UNSIGNED DEFAULT 0, created_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, UNIQUE INDEX unique_badge_name (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_badges (id INT UNSIGNED AUTO_INCREMENT NOT NULL, user_id INT UNSIGNED NOT NULL, badge_id INT UNSIGNED NOT NULL, earned_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, INDEX idx_user_badges (user_id, badge_id), UNIQUE INDEX unique_user_badge (user_id, badge_id), INDEX badge_id (badge_id), INDEX IDX_1DA448A7A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_roles (id INT UNSIGNED AUTO_INCREMENT NOT NULL, user_id INT UNSIGNED NOT NULL, role ENUM(\'student\', \'instructor\', \'admin\') CHARACTER SET utf8mb4 DEFAULT \'\'\'student\'\'\' COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, UNIQUE INDEX unique_user_role (user_id, role), INDEX IDX_54FCD59FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_settings (id INT UNSIGNED AUTO_INCREMENT NOT NULL, user_id INT UNSIGNED NOT NULL, setting_key VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, setting_value TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, updated_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, INDEX idx_user_settings (user_id), UNIQUE INDEX unique_user_setting (user_id, setting_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_social_links (id INT UNSIGNED AUTO_INCREMENT NOT NULL, user_id INT UNSIGNED NOT NULL, platform VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, url VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, handle VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, created_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, UNIQUE INDEX unique_user_platform (user_id, platform), INDEX IDX_1BDE6988A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_badges ADD CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_badges ADD CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (badge_id) REFERENCES badges (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_settings ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_social_links ADD CONSTRAINT `user_social_links_ibfk_1` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event CHANGE description description TEXT DEFAULT NULL, CHANGE image image VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT `event_ibfk_1` FOREIGN KEY (salle_id) REFERENCES salle (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('CREATE INDEX salle_id ON event (salle_id)');
        $this->addSql('ALTER TABLE reservation ADD user VARCHAR(255) DEFAULT \'NULL\', CHANGE adresse adresse VARCHAR(255) DEFAULT \'NULL\', CHANGE date_reservation date_reservation DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT `fk_reservation_user` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT `reservation_ibfk_1` FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT `reservation_ibfk_2` FOREIGN KEY (salle_id) REFERENCES salle (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('CREATE INDEX event_id ON reservation (event_id)');
        $this->addSql('CREATE INDEX salle_id ON reservation (salle_id)');
        $this->addSql('CREATE INDEX fk_reservation_user ON reservation (user_id)');
        $this->addSql('ALTER TABLE salle DROP event_id, CHANGE image_3d image_3d VARCHAR(255) DEFAULT \'NULL\', CHANGE equipment equipment TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD avatar VARCHAR(500) DEFAULT \'NULL\', ADD bio TEXT DEFAULT NULL, ADD location VARCHAR(255) DEFAULT \'NULL\', ADD website VARCHAR(255) DEFAULT \'NULL\', ADD email_verified_at DATETIME DEFAULT \'NULL\', ADD remember_token VARCHAR(100) DEFAULT \'NULL\', ADD xp INT UNSIGNED DEFAULT 0, ADD level INT UNSIGNED DEFAULT 1, ADD level_name VARCHAR(50) DEFAULT \'\'\'Newcomer\'\'\', ADD streak INT UNSIGNED DEFAULT 0, ADD current_streak_start DATE DEFAULT \'NULL\', ADD updated_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, CHANGE first_name first_name VARCHAR(100) DEFAULT \'NULL\', CHANGE last_name last_name VARCHAR(100) DEFAULT \'NULL\', CHANGE last_login_at last_login_at DATETIME DEFAULT \'NULL\', CHANGE created_at created_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL');
        $this->addSql('CREATE INDEX idx_xp ON users (xp)');
        $this->addSql('CREATE INDEX idx_email ON users (email)');
        $this->addSql('CREATE INDEX idx_streak ON users (streak)');
        $this->addSql('CREATE INDEX idx_username ON users (username)');
        $this->addSql('CREATE INDEX idx_level ON users (level)');
        $this->addSql('ALTER TABLE users RENAME INDEX uniq_1483a5e9e7927c74 TO email');
        $this->addSql('ALTER TABLE users RENAME INDEX uniq_1483a5e9f85e0677 TO username');
    }
}
