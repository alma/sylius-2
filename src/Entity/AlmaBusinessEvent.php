<?php

declare(strict_types=1);

namespace Alma\Sylius\Entity;

use Alma\Sylius\Repository\AlmaBusinessEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\OrderInterface;

#[ORM\Entity(repositoryClass: AlmaBusinessEventRepository::class)]
#[ORM\Table(name: 'alma_business_event')]
class AlmaBusinessEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    // Interface target, for the reason given on AlmaPaymentReference::$syliusPayment.
    #[ORM\OneToOne(targetEntity: OrderInterface::class)]
    #[ORM\JoinColumn(name: 'sylius_order_id', referencedColumnName: 'id', unique: true, nullable: false, onDelete: 'CASCADE')]
    private OrderInterface $syliusOrder;

    #[ORM\Column(name: 'cart_reported_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cartReportedAt = null;

    #[ORM\Column(name: 'was_bnpl_eligible', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $wasBnplEligible = false;

    #[ORM\Column(name: 'order_confirmed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $orderConfirmedAt = null;

    public function __construct(OrderInterface $syliusOrder)
    {
        $this->syliusOrder = $syliusOrder;
    }
}
