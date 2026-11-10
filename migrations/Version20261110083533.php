<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110083533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE categories RENAME INDEX uq_categories_name TO UNIQ_3AF346685E237E06');
        $this->addSql('ALTER TABLE tickets DROP FOREIGN KEY `fk_tickets_user`');
        $this->addSql('DROP INDEX fk_tickets_user ON tickets');
        $this->addSql('ALTER TABLE tickets CHANGE code code VARCHAR(8) NOT NULL, CHANGE status status VARCHAR(20) NOT NULL, CHANGE user_id assignee_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE tickets ADD CONSTRAINT FK_54469DF459EC7D60 FOREIGN KEY (assignee_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_54469DF459EC7D60 ON tickets (assignee_id)');
        $this->addSql('ALTER TABLE tickets RENAME INDEX uq_tickets_code TO UNIQ_54469DF477153098');
        $this->addSql('ALTER TABLE tickets RENAME INDEX fk_tickets_category TO IDX_54469DF412469DE2');
        $this->addSql('ALTER TABLE users RENAME INDEX uq_users_email TO UNIQ_1483A5E9E7927C74');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE categories RENAME INDEX uniq_3af346685e237e06 TO uq_categories_name');
        $this->addSql('ALTER TABLE tickets DROP FOREIGN KEY FK_54469DF459EC7D60');
        $this->addSql('DROP INDEX IDX_54469DF459EC7D60 ON tickets');
        $this->addSql('ALTER TABLE tickets CHANGE code code CHAR(8) NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'\'\'open\'\'\' NOT NULL, CHANGE assignee_id user_id BIGINT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE tickets ADD CONSTRAINT `fk_tickets_user` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX fk_tickets_user ON tickets (user_id)');
        $this->addSql('ALTER TABLE tickets RENAME INDEX idx_54469df412469de2 TO fk_tickets_category');
        $this->addSql('ALTER TABLE tickets RENAME INDEX uniq_54469df477153098 TO uq_tickets_code');
        $this->addSql('ALTER TABLE users RENAME INDEX uniq_1483a5e9e7927c74 TO uq_users_email');
    }
}
