<?php

declare(strict_types=1);

namespace Alma\Sylius\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * An order paid In-Page carries one Alma payment per attempt, all hanging on
 * its single Sylius Payment (cf. specs-sylius/in-page): the reference is no
 * longer unique per Sylius Payment, and records each attempt's plan, flow and
 * whether Alma reported it paid.
 */
final class Version20261029100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record every Alma payment attempt of an order on alma_payment_reference (plan key, origin, paid date).';
    }

    public function up(Schema $schema): void
    {
        // The foreign key needs an index on sylius_payment_id once the unique one is gone.
        $this->addSql('CREATE INDEX IDX_alma_payment_reference_sylius_payment_id ON alma_payment_reference (sylius_payment_id)');
        $this->addSql('DROP INDEX UNIQ_alma_payment_reference_sylius_payment_id ON alma_payment_reference');
        $this->addSql(
            "ALTER TABLE alma_payment_reference
                ADD fee_plan_key VARCHAR(64) DEFAULT NULL,
                ADD origin VARCHAR(32) NOT NULL DEFAULT 'online',
                ADD paid_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'"
        );
        $this->addSql(
            "UPDATE alma_payment_reference reference
                INNER JOIN sylius_payment payment ON payment.id = reference.sylius_payment_id
                SET reference.fee_plan_key = JSON_UNQUOTE(JSON_EXTRACT(payment.details, '$.alma_fee_plan_key'))
                WHERE JSON_EXTRACT(payment.details, '$.alma_fee_plan_key') IS NOT NULL
                  AND JSON_TYPE(JSON_EXTRACT(payment.details, '$.alma_fee_plan_key')) = 'STRING'"
        );
        $this->addSql(
            "UPDATE alma_payment_reference reference
                INNER JOIN sylius_payment payment ON payment.id = reference.sylius_payment_id
                SET reference.paid_at = payment.updated_at
                WHERE payment.state IN ('completed', 'refunded')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE alma_payment_reference DROP fee_plan_key, DROP origin, DROP paid_at');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_alma_payment_reference_sylius_payment_id ON alma_payment_reference (sylius_payment_id)');
        $this->addSql('DROP INDEX IDX_alma_payment_reference_sylius_payment_id ON alma_payment_reference');
    }
}
