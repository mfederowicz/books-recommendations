<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create users_failed_logins table for login throttling
 */
final class Version20251209100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users_failed_logins table for login throttling functionality';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users_failed_logins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            attempts_count INT NOT NULL DEFAULT 1,
            last_attempt_at DATETIME NOT NULL,
            blocked_until DATETIME NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX email_idx (email),
            INDEX blocked_until_idx (blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users_failed_logins');
    }
}




