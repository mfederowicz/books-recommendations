<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix database indexes to match entity definitions - preserve existing working indexes
 */
final class Version20251209110053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix database indexes to match entity definitions - preserve existing working indexes';
    }

    public function up(Schema $schema): void
    {
        // Dodajemy indeksy pod oczekiwanymi nazwami Doctrine obok istniejących indeksów
        // To pozwoli Doctrine uznać schemat za zsynchronizowany bez usuwania działających indeksów

        // Dodajemy indeksy które Doctrine oczekuje (mogą już istnieć pod innymi nazwami)
        $this->addSql('CREATE INDEX idx_normalized_hash ON recommendations_embeddings (normalized_text_hash)');
        $this->addSql('CREATE INDEX idx_tag_recommendation ON recommendations_tags (tag_id, recommendation_id)');
        $this->addSql('CREATE INDEX idx_ascii ON tags (ascii)');
        $this->addSql('CREATE INDEX idx_name ON tags (name)');
    }

    public function down(Schema $schema): void
    {
        // Usuwamy indeksy dodane w tej migracji
        $this->addSql('DROP INDEX idx_normalized_hash ON recommendations_embeddings');
        $this->addSql('DROP INDEX idx_tag_recommendation ON recommendations_tags');
        $this->addSql('DROP INDEX idx_ascii ON tags');
        $this->addSql('DROP INDEX idx_name ON tags');
    }
}
