<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211001358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update ebooks schema: change ISBN to VARCHAR(13), add main_description and tags to ebooks, change ebook_id to VARCHAR(13) ISBN and add payload_description to ebooks_embeddings';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ebooks ADD main_description LONGTEXT DEFAULT NULL, ADD tags LONGTEXT DEFAULT NULL, CHANGE isbn isbn VARCHAR(13) NOT NULL');
        $this->addSql('ALTER TABLE ebooks_embeddings DROP FOREIGN KEY `FK_4A7F05B216CB1DB`');
        $this->addSql('ALTER TABLE ebooks_embeddings ADD payload_description LONGTEXT DEFAULT NULL, CHANGE ebook_id ebook_id VARCHAR(13) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ebooks DROP main_description, DROP tags, CHANGE isbn isbn VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE ebooks_embeddings DROP payload_description, CHANGE ebook_id ebook_id INT NOT NULL');
        $this->addSql('ALTER TABLE ebooks_embeddings ADD CONSTRAINT `FK_4A7F05B216CB1DB` FOREIGN KEY (ebook_id) REFERENCES ebooks (id) ON UPDATE NO ACTION ON DELETE CASCADE');
    }
}
