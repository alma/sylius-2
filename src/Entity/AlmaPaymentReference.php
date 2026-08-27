<?php

declare(strict_types=1);

namespace Alma\Sylius\Entity;

use Alma\Sylius\Repository\AlmaPaymentReferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;

/**
 * Indexed lookup row: Alma payment id → Sylius Payment + Order + persisted mode.
 *
 * Maintained in double persistence with `sylius_payment.details` (D4 / ECOM-4139):
 * `AlmaCaptureAction` writes both at place-order time. The Sylius `details`
 * payload remains the legacy source for any downstream consumer not yet
 * migrated (and to keep B2B/back-office tools that introspect `details` happy);
 * this dedicated entity is the indexed, ORM-native lookup path used by
 * `AlmaPaymentResolver` for IPN/return/refund.
 *
 * Avoids the need for a JSON-query DQL function and gives direct FK access to
 * Order and Payment from a `pid` — answering the « avoir l'order sans passer
 * par le paiement Alma » concern surfaced in the post-Phase F audit (2026-05-19).
 */
#[ORM\Entity(repositoryClass: AlmaPaymentReferenceRepository::class)]
#[ORM\Table(name: 'alma_payment_reference')]
class AlmaPaymentReference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'alma_payment_id', type: Types::STRING, length: 255, unique: true)]
    private string $almaPaymentId;

    #[ORM\Column(name: 'alma_mode', type: Types::STRING, length: 8)]
    private string $almaMode;

    // targetEntity = INTERFACE, not concrete class. The Sylius app commonly overrides
    // the Payment / Order concrete model (e.g. App\Entity\Payment\Payment via the
    // `sylius_payment.resources.payment.classes.model` resource config). Hardcoding
    // the Sylius core concrete class here causes Doctrine to load this association as
    // a different instance than the rest of the app, breaking the UoW identity map
    // and silently disabling the workflow cascade (cf. ResolveOrderPaymentStateListener
    // iterating order.payments and finding our transitioned payment as a stale copy).
    // Using the interface lets Sylius's ResolveTargetEntityListener resolve it to the
    // app-configured concrete class at runtime.
    #[ORM\ManyToOne(targetEntity: SyliusPaymentInterface::class)]
    #[ORM\JoinColumn(name: 'sylius_payment_id', referencedColumnName: 'id', unique: true, nullable: false, onDelete: 'CASCADE')]
    private SyliusPaymentInterface $syliusPayment;

    #[ORM\ManyToOne(targetEntity: OrderInterface::class)]
    #[ORM\JoinColumn(name: 'sylius_order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private OrderInterface $syliusOrder;

    public function __construct(
        string $almaPaymentId,
        string $almaMode,
        SyliusPaymentInterface $syliusPayment,
        OrderInterface $syliusOrder,
    ) {
        $this->almaPaymentId = $almaPaymentId;
        $this->almaMode = $almaMode;
        $this->syliusPayment = $syliusPayment;
        $this->syliusOrder = $syliusOrder;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlmaPaymentId(): string
    {
        return $this->almaPaymentId;
    }

    public function getAlmaMode(): string
    {
        return $this->almaMode;
    }

    public function getSyliusPayment(): SyliusPaymentInterface
    {
        return $this->syliusPayment;
    }

    public function getSyliusOrder(): OrderInterface
    {
        return $this->syliusOrder;
    }
}
