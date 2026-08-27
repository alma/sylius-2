<?php

declare(strict_types=1);

namespace Alma\Sylius\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create alma_configuration singleton table (B1 — ECOM-4120).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE alma_configuration (
                id INT AUTO_INCREMENT NOT NULL,
                api_key_live VARCHAR(255) DEFAULT NULL,
                api_key_test VARCHAR(255) DEFAULT NULL,
                api_mode VARCHAR(8) NOT NULL,
                merchant_id VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE alma_configuration');
    }
}
