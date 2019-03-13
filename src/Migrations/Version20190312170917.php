<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190312170917 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE invite (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, invite_key CLOB DEFAULT NULL)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__file AS SELECT id, corr_uid, filetype, org_filename, filename FROM file');
        $this->addSql('DROP TABLE file');
        $this->addSql('CREATE TABLE file (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, corr_uid INTEGER NOT NULL, filetype CLOB NOT NULL COLLATE BINARY, org_filename CLOB NOT NULL COLLATE BINARY, filename CLOB NOT NULL)');
        $this->addSql('INSERT INTO file (id, corr_uid, filetype, org_filename, filename) SELECT id, corr_uid, filetype, org_filename, filename FROM __temp__file');
        $this->addSql('DROP TABLE __temp__file');
        $this->addSql('CREATE TEMPORARY TABLE __temp__paste AS SELECT id, corr_uid, paste_name, paste_text, real_id FROM paste');
        $this->addSql('DROP TABLE paste');
        $this->addSql('CREATE TABLE paste (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, corr_uid INTEGER NOT NULL, paste_name CLOB NOT NULL COLLATE BINARY, paste_text CLOB NOT NULL COLLATE BINARY, real_id CLOB NOT NULL)');
        $this->addSql('INSERT INTO paste (id, corr_uid, paste_name, paste_text, real_id) SELECT id, corr_uid, paste_name, paste_text, real_id FROM __temp__paste');
        $this->addSql('DROP TABLE __temp__paste');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE invite');
        $this->addSql('CREATE TEMPORARY TABLE __temp__file AS SELECT id, corr_uid, filetype, filename, org_filename FROM file');
        $this->addSql('DROP TABLE file');
        $this->addSql('CREATE TABLE file (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, corr_uid INTEGER NOT NULL, filetype CLOB NOT NULL, org_filename CLOB NOT NULL, filename CLOB DEFAULT NULL COLLATE BINARY)');
        $this->addSql('INSERT INTO file (id, corr_uid, filetype, filename, org_filename) SELECT id, corr_uid, filetype, filename, org_filename FROM __temp__file');
        $this->addSql('DROP TABLE __temp__file');
        $this->addSql('CREATE TEMPORARY TABLE __temp__paste AS SELECT id, corr_uid, paste_name, paste_text, real_id FROM paste');
        $this->addSql('DROP TABLE paste');
        $this->addSql('CREATE TABLE paste (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, corr_uid INTEGER NOT NULL, paste_name CLOB NOT NULL, paste_text CLOB NOT NULL, real_id CLOB DEFAULT NULL COLLATE BINARY)');
        $this->addSql('INSERT INTO paste (id, corr_uid, paste_name, paste_text, real_id) SELECT id, corr_uid, paste_name, paste_text, real_id FROM __temp__paste');
        $this->addSql('DROP TABLE __temp__paste');
    }
}
