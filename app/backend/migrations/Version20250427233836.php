<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250427233836 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE expense (id INT AUTO_INCREMENT NOT NULL, paid_by_id INT NOT NULL, group_id INT NOT NULL, title VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) NOT NULL, date DATE NOT NULL, category VARCHAR(255) NOT NULL, receipt VARCHAR(255) DEFAULT NULL, INDEX IDX_2D3A8DA67F9BC654 (paid_by_id), INDEX IDX_2D3A8DA6FE54D947 (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE expense_concerned_users (expense_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_20A74B30F395DB7B (expense_id), INDEX IDX_20A74B30A76ED395 (user_id), PRIMARY KEY(expense_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE `group` (id INT AUTO_INCREMENT NOT NULL, created_by_id INT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_6DC044C5B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE group_members (group_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_C3A086F3FE54D947 (group_id), INDEX IDX_C3A086F3A76ED395 (user_id), PRIMARY KEY(group_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA67F9BC654 FOREIGN KEY (paid_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA6FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense_concerned_users ADD CONSTRAINT FK_20A74B30F395DB7B FOREIGN KEY (expense_id) REFERENCES expense (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense_concerned_users ADD CONSTRAINT FK_20A74B30A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C5B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE group_members ADD CONSTRAINT FK_C3A086F3FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE group_members ADD CONSTRAINT FK_C3A086F3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA67F9BC654
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense DROP FOREIGN KEY FK_2D3A8DA6FE54D947
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense_concerned_users DROP FOREIGN KEY FK_20A74B30F395DB7B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE expense_concerned_users DROP FOREIGN KEY FK_20A74B30A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C5B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE group_members DROP FOREIGN KEY FK_C3A086F3FE54D947
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE group_members DROP FOREIGN KEY FK_C3A086F3A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE expense
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE expense_concerned_users
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE `group`
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE group_members
        SQL);
    }
}
