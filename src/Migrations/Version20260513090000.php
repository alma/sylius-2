<?php

declare(strict_types=1);

namespace Alma\Sylius\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fee_plans JSON column to alma_configuration (B5 — ECOM-4124).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE alma_configuration ADD fee_plans JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE alma_configuration DROP fee_plans');
    }
}
