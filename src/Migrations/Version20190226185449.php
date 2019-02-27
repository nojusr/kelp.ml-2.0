<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190226185449 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE paste (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, corr_uid INTEGER NOT NULL, paste_name CLOB NOT NULL, paste_text CLOB NOT NULL)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__file AS SELECT id, corr_uid, filetype, filename, org_filename FROM file');
        $this->addSql('DROP TABLE file');
        $this->addSql('CREATE TABLE file (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, corr_uid INTEGER NOT NULL, filetype CLOB NOT NULL COLLATE BINARY, org_filename CLOB NOT NULL COLLATE BINARY, filename CLOB NOT NULL)');
        $this->addSql('INSERT INTO file (id, corr_uid, filetype, filename, org_filename) SELECT id, corr_uid, filetype, filename, org_filename FROM __temp__file');
        $this->addSql('DROP TABLE __temp__file');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, date_added, username, password, access_level, api_key FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username CLOB NOT NULL COLLATE BINARY, password CLOB NOT NULL COLLATE BINARY, access_level INTEGER NOT NULL, api_key CLOB NOT NULL COLLATE BINARY, date_added DATETIME NOT NULL)');
        $this->addSql('INSERT INTO user (id, date_added, username, password, access_level, api_key) SELECT id, date_added, username, password, access_level, api_key FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE paste');
        $this->addSql('CREATE TEMPORARY TABLE __temp__file AS SELECT id, corr_uid, filetype, filename, org_filename FROM file');
        $this->addSql('DROP TABLE file');
        $this->addSql('CREATE TABLE file (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, corr_uid INTEGER NOT NULL, filetype CLOB NOT NULL, org_filename CLOB NOT NULL, filename CLOB DEFAULT NULL COLLATE BINARY)');
        $this->addSql('INSERT INTO file (id, corr_uid, filetype, filename, org_filename) SELECT id, corr_uid, filetype, filename, org_filename FROM __temp__file');
        $this->addSql('DROP TABLE __temp__file');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, date_added, username, password, access_level, api_key FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username CLOB NOT NULL, password CLOB NOT NULL, access_level INTEGER NOT NULL, api_key CLOB NOT NULL, date_added DATE NOT NULL)');
        $this->addSql('INSERT INTO user (id, date_added, username, password, access_level, api_key) SELECT id, date_added, username, password, access_level, api_key FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
    }
}
