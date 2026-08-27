<?php

declare(strict_types=1);

namespace Alma\Sylius\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fee_plan_overrides JSON column to alma_configuration (B6 — ECOM-4125).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE alma_configuration ADD fee_plan_overrides JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE alma_configuration DROP fee_plan_overrides');
    }
}
