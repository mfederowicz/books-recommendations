<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create missing tables for recommendations system: recommendations, recommendations_embeddings, recommendations_tags, ebooks, ebooks_embeddings
 */
final class Version20251209073733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create missing tables for recommendations system: recommendations, recommendations_embeddings, recommendations_tags, ebooks, ebooks_embeddings';
    }

    public function up(Schema $schema): void
    {
        // Create recommendations table
        $this->addSql('CREATE TABLE recommendations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            short_description LONGTEXT NOT NULL,
            normalized_text_hash VARCHAR(64) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            user_id INT NOT NULL,
            INDEX IDX_73904ED7A76ED395 (user_id),
            UNIQUE INDEX unique_user_text_hash (user_id, normalized_text_hash)
        ) DEFAULT CHARACTER SET utf8mb4');

        // Create recommendations_embeddings table
        $this->addSql('CREATE TABLE recommendations_embeddings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            normalized_text_hash VARCHAR(64) NOT NULL,
            description LONGTEXT NOT NULL,
            embedding JSON NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            UNIQUE INDEX normalized_text_hash (normalized_text_hash)
        ) DEFAULT CHARACTER SET utf8mb4');

        // Create recommendations_tags table (many-to-many relationship)
        $this->addSql('CREATE TABLE recommendations_tags (
            recommendation_id INT NOT NULL,
            tag_id INT NOT NULL,
            INDEX IDX_8B86FABFD173940B (recommendation_id),
            INDEX IDX_8B86FABFBAD26311 (tag_id),
            PRIMARY KEY (recommendation_id, tag_id)
        ) DEFAULT CHARACTER SET utf8mb4');

        // Create ebooks table
        $this->addSql('CREATE TABLE ebooks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            isbn VARCHAR(20) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) NOT NULL,
            offers_count INT NOT NULL DEFAULT 0,
            comparison_link VARCHAR(512) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            INDEX idx_isbn (isbn),
            INDEX idx_title_author (title, author)
        ) DEFAULT CHARACTER SET utf8mb4');

        // Create ebooks_embeddings table
        $this->addSql('CREATE TABLE ebooks_embeddings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ebook_id INT NOT NULL,
            vector JSON NOT NULL,
            payload_title VARCHAR(255) NOT NULL,
            payload_author VARCHAR(255) NOT NULL,
            payload_tags JSON NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            UNIQUE INDEX uk_ebook_id (ebook_id)
        ) DEFAULT CHARACTER SET utf8mb4');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE recommendations ADD CONSTRAINT FK_73904ED7A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recommendations_tags ADD CONSTRAINT FK_8B86FABFD173940B FOREIGN KEY (recommendation_id) REFERENCES recommendations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recommendations_tags ADD CONSTRAINT FK_8B86FABFBAD26311 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ebooks_embeddings ADD CONSTRAINT FK_4A7F05B216CB1DB FOREIGN KEY (ebook_id) REFERENCES ebooks (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ebooks_embeddings');
        $this->addSql('DROP TABLE ebooks');
        $this->addSql('DROP TABLE recommendations_tags');
        $this->addSql('DROP TABLE recommendations_embeddings');
        $this->addSql('DROP TABLE recommendations');
    }
}
