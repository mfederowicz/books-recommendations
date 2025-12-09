<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create tags table for book recommendations
 */
final class Version20251209041907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tags table for book recommendations system';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE COLLATE utf8mb4_polish_ci,
            ascii VARCHAR(50) NOT NULL UNIQUE,
            active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_ascii (ascii)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tags');
    }
}
