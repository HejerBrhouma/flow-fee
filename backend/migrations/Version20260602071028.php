<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260602071028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE budgets (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, period VARCHAR(10) NOT NULL, year INT NOT NULL, month INT DEFAULT NULL, department_id INT NOT NULL, INDEX IDX_DCAA9548AE80F5DF (department_id), UNIQUE INDEX UNIQ_DCAA9548AE80F5DFBB8273378EB61006 (department_id, year, month), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE categories (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, icon VARCHAR(100) DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, company_id INT DEFAULT NULL, INDEX IDX_3AF34668979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE companies (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, siret VARCHAR(14) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, zip_code VARCHAR(10) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8244AA3A26E94372 (siret), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE departments (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, monthly_budget NUMERIC(10, 2) DEFAULT NULL, yearly_budget NUMERIC(10, 2) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INT NOT NULL, INDEX IDX_16AEB8D4979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE expense_receipts (id INT AUTO_INCREMENT NOT NULL, file_path VARCHAR(255) NOT NULL, original_name VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(50) DEFAULT NULL, file_size INT DEFAULT NULL, uploaded_at DATETIME NOT NULL, expense_id INT NOT NULL, INDEX IDX_DB8A215BF395DB7B (expense_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE expenses (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL, expense_date DATE NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, review_comment LONGTEXT DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, category_id INT DEFAULT NULL, department_id INT DEFAULT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_2496F35BA76ED395 (user_id), INDEX IDX_2496F35B12469DE2 (category_id), INDEX IDX_2496F35BAE80F5DF (department_id), INDEX IDX_2496F35BFC6B21F1 (reviewed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, message VARCHAR(255) NOT NULL, data JSON DEFAULT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_6000B0D3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_companies (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(50) NOT NULL, joined_at DATETIME NOT NULL, user_id INT NOT NULL, company_id INT NOT NULL, department_id INT DEFAULT NULL, INDEX IDX_82A427DEA76ED395 (user_id), INDEX IDX_82A427DE979B1AD6 (company_id), INDEX IDX_82A427DEAE80F5DF (department_id), UNIQUE INDEX UNIQ_82A427DEA76ED395979B1AD6 (user_id, company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, type VARCHAR(20) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, google_id VARCHAR(255) DEFAULT NULL, facebook_id VARCHAR(255) DEFAULT NULL, is_verified TINYINT NOT NULL, email_verification_token VARCHAR(255) DEFAULT NULL, password_reset_token VARCHAR(255) DEFAULT NULL, password_reset_token_expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE budgets ADD CONSTRAINT FK_DCAA9548AE80F5DF FOREIGN KEY (department_id) REFERENCES departments (id)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('ALTER TABLE departments ADD CONSTRAINT FK_16AEB8D4979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('ALTER TABLE expense_receipts ADD CONSTRAINT FK_DB8A215BF395DB7B FOREIGN KEY (expense_id) REFERENCES expenses (id)');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_2496F35BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_2496F35B12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id)');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_2496F35BAE80F5DF FOREIGN KEY (department_id) REFERENCES departments (id)');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_2496F35BFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE user_companies ADD CONSTRAINT FK_82A427DEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE user_companies ADD CONSTRAINT FK_82A427DE979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('ALTER TABLE user_companies ADD CONSTRAINT FK_82A427DEAE80F5DF FOREIGN KEY (department_id) REFERENCES departments (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE budgets DROP FOREIGN KEY FK_DCAA9548AE80F5DF');
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY FK_3AF34668979B1AD6');
        $this->addSql('ALTER TABLE departments DROP FOREIGN KEY FK_16AEB8D4979B1AD6');
        $this->addSql('ALTER TABLE expense_receipts DROP FOREIGN KEY FK_DB8A215BF395DB7B');
        $this->addSql('ALTER TABLE expenses DROP FOREIGN KEY FK_2496F35BA76ED395');
        $this->addSql('ALTER TABLE expenses DROP FOREIGN KEY FK_2496F35B12469DE2');
        $this->addSql('ALTER TABLE expenses DROP FOREIGN KEY FK_2496F35BAE80F5DF');
        $this->addSql('ALTER TABLE expenses DROP FOREIGN KEY FK_2496F35BFC6B21F1');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE user_companies DROP FOREIGN KEY FK_82A427DEA76ED395');
        $this->addSql('ALTER TABLE user_companies DROP FOREIGN KEY FK_82A427DE979B1AD6');
        $this->addSql('ALTER TABLE user_companies DROP FOREIGN KEY FK_82A427DEAE80F5DF');
        $this->addSql('DROP TABLE budgets');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE companies');
        $this->addSql('DROP TABLE departments');
        $this->addSql('DROP TABLE expense_receipts');
        $this->addSql('DROP TABLE expenses');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE user_companies');
        $this->addSql('DROP TABLE users');
    }
}
