<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260813130236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE device_tokens (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) NOT NULL, platform VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_794A60955F37A13B (token), INDEX IDX_794A6095A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE device_tokens ADD CONSTRAINT FK_794A6095A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE users CHANGE two_factor_enabled two_factor_enabled TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device_tokens DROP FOREIGN KEY FK_794A6095A76ED395');
        $this->addSql('DROP TABLE device_tokens');
        $this->addSql('ALTER TABLE users CHANGE two_factor_enabled two_factor_enabled TINYINT DEFAULT 0 NOT NULL');
    }
}
