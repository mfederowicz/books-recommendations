<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add role column to users table
 */
final class Version20251209003616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role column to users table with default value "user"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD role VARCHAR(50) NOT NULL DEFAULT \'user\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN role');
    }
}


