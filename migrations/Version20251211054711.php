<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211054711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recommendation results functionality with found_books_count, last_search_at fields and recommendation_results table';
    }

    public function up(Schema $schema): void
    {
        // Add fields to recommendations table
        $this->addSql('ALTER TABLE recommendations ADD found_books_count INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE recommendations ADD last_search_at DATETIME NULL');

        // Create recommendation_results table
        $this->addSql('CREATE TABLE recommendation_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recommendation_id INT NOT NULL,
            ebook_id INT NOT NULL,
            similarity_score DECIMAL(5,4) NOT NULL,
            rank_order INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (recommendation_id) REFERENCES recommendations(id) ON DELETE CASCADE,
            FOREIGN KEY (ebook_id) REFERENCES ebooks(id) ON DELETE CASCADE,
            UNIQUE KEY uk_recommendation_ebook (recommendation_id, ebook_id),
            INDEX idx_recommendation_similarity (recommendation_id, similarity_score DESC),
            INDEX idx_recommendation_rank (recommendation_id, rank_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci');
    }

    public function down(Schema $schema): void
    {
        // Drop recommendation_results table
        $this->addSql('DROP TABLE recommendation_results');

        // Remove fields from recommendations table
        $this->addSql('ALTER TABLE recommendations DROP found_books_count');
        $this->addSql('ALTER TABLE recommendations DROP last_search_at');
    }
}
