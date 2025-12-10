<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251209112526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_isbn ON ebooks');
        $this->addSql('DROP INDEX idx_title_author ON ebooks');
        $this->addSql('ALTER TABLE ebooks_embeddings CHANGE ebook_id ebook_id INT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_tag_recommendation ON recommendations_tags');
        $this->addSql('DROP INDEX idx_normalized_hash ON recommendations_embeddings');
        $this->addSql('DROP INDEX idx_ascii ON tags');
        $this->addSql('DROP INDEX idx_name ON tags');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_isbn ON ebooks (isbn)');
        $this->addSql('CREATE INDEX idx_title_author ON ebooks (title, author)');
        $this->addSql('ALTER TABLE ebooks_embeddings CHANGE ebook_id ebook_id INT NOT NULL');
        $this->addSql('CREATE INDEX idx_normalized_hash ON recommendations_embeddings (normalized_text_hash)');
        $this->addSql('CREATE INDEX idx_tag_recommendation ON recommendations_tags (tag_id, recommendation_id)');
        $this->addSql('CREATE INDEX idx_ascii ON tags (ascii)');
        $this->addSql('CREATE INDEX idx_name ON tags (name)');
    }
}
