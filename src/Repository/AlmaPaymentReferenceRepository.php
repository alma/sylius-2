<?php

declare(strict_types=1);

namespace Alma\Sylius\Repository;

use Alma\Sylius\Entity\AlmaPaymentReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * @extends ServiceEntityRepository<AlmaPaymentReference>
 */
class AlmaPaymentReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlmaPaymentReference::class);
    }

    public function findOneByAlmaPaymentId(string $almaPaymentId): ?AlmaPaymentReference
    {
        if ($almaPaymentId === '') {
            return null;
        }

        return $this->findOneBy(['almaPaymentId' => $almaPaymentId]);
    }

    /**
     * Every Alma payment created for the order, oldest first.
     *
     * @return list<AlmaPaymentReference>
     */
    public function findByOrder(OrderInterface $order): array
    {
        return array_values($this->findBy(['syliusOrder' => $order], ['id' => 'ASC']));
    }
}
