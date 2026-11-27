<?php

declare(strict_types=1);

namespace Alma\Sylius\Repository;

use Alma\Sylius\Entity\AlmaBusinessEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Writes go straight through the connection: they happen while rendering the
 * checkout or after the response, where flushing the entity manager would
 * also write whatever else is pending in it.
 *
 * @extends ServiceEntityRepository<AlmaBusinessEvent>
 */
class AlmaBusinessEventRepository extends ServiceEntityRepository
{
    private const TABLE = 'alma_business_event';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlmaBusinessEvent::class);
    }

    public function isCartReported(int $orderId): bool
    {
        return $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE sylius_order_id = ? AND cart_reported_at IS NOT NULL',
            [$orderId],
        ) !== false;
    }

    public function markCartReported(int $orderId, \DateTimeImmutable $reportedAt): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (sylius_order_id, cart_reported_at, was_bnpl_eligible) VALUES (?, ?, 0)
                ON DUPLICATE KEY UPDATE cart_reported_at = VALUES(cart_reported_at)',
            [$orderId, $reportedAt],
            [Types::INTEGER, Types::DATETIME_IMMUTABLE],
        );
    }

    public function saveEligibility(int $orderId, bool $wasBnplEligible): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (sylius_order_id, was_bnpl_eligible) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE was_bnpl_eligible = VALUES(was_bnpl_eligible)',
            [$orderId, $wasBnplEligible],
            [Types::INTEGER, Types::BOOLEAN],
        );
    }

    /**
     * Claims the order's confirmation before it is sent, so that the return
     * route and the IPN completing the same order never both send it.
     *
     * @return bool|null the cart's last checkout eligibility, or null when the
     *                   cart was never reported or the order already claimed
     */
    public function claimOrderConfirmation(int $orderId, \DateTimeImmutable $confirmedAt): ?bool
    {
        $connection = $this->getEntityManager()->getConnection();
        $claimed = $connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET order_confirmed_at = ?
                WHERE sylius_order_id = ? AND cart_reported_at IS NOT NULL AND order_confirmed_at IS NULL',
            [$confirmedAt, $orderId],
            [Types::DATETIME_IMMUTABLE, Types::INTEGER],
        );
        if ($claimed === 0) {
            return null;
        }

        return (bool) $connection->fetchOne(
            'SELECT was_bnpl_eligible FROM ' . self::TABLE . ' WHERE sylius_order_id = ?',
            [$orderId],
        );
    }
}
