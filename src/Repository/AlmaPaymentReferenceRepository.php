<?php

declare(strict_types=1);

namespace Alma\Sylius\Repository;

use Alma\Sylius\Entity\AlmaPaymentReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
}
