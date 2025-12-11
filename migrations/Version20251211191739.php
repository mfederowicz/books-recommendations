<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211191739 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_recommendation_rank ON recommendation_results');
        $this->addSql('DROP INDEX idx_recommendation_similarity ON recommendation_results');
        $this->addSql('ALTER TABLE recommendation_results CHANGE similarity_score similarity_score DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE recommendation_results RENAME INDEX ebook_id TO IDX_C2C0EDB876E71D49');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recommendation_results CHANGE similarity_score similarity_score NUMERIC(5, 4) NOT NULL');
        $this->addSql('CREATE INDEX idx_recommendation_rank ON recommendation_results (recommendation_id, rank_order)');
        $this->addSql('CREATE INDEX idx_recommendation_similarity ON recommendation_results (recommendation_id, similarity_score)');
        $this->addSql('ALTER TABLE recommendation_results RENAME INDEX idx_c2c0edb876e71d49 TO ebook_id');
    }
}
