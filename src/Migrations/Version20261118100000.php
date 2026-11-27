<?php

declare(strict_types=1);

namespace Alma\Sylius\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add alma_business_event: business events state of each Sylius cart.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE alma_business_event (
                id INT AUTO_INCREMENT NOT NULL,
                sylius_order_id INT NOT NULL,
                cart_reported_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                was_bnpl_eligible TINYINT(1) DEFAULT 0 NOT NULL,
                order_confirmed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX UNIQ_alma_business_event_sylius_order_id (sylius_order_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB"
        );
        $this->addSql(
            'ALTER TABLE alma_business_event
                ADD CONSTRAINT FK_alma_business_event_sylius_order
                FOREIGN KEY (sylius_order_id) REFERENCES sylius_order (id) ON DELETE CASCADE'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE alma_business_event');
    }
}
