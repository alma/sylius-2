<?php

declare(strict_types=1);

namespace Tests\Alma\Sylius\Checkout;

use Alma\Sylius\Api\FeePlanAdapter;
use Alma\Sylius\Checkout\FeePlanGrouper;
use Alma\Sylius\Checkout\PlanGroup;
use Alma\Sylius\Eligibility\AlmaEligibility;
use PHPUnit\Framework\TestCase;

final class FeePlanGrouperTest extends TestCase
{
    private const TODAY = '2026-09-27';

    private FeePlanGrouper $grouper;

    protected function setUp(): void
    {
        date_default_timezone_set('UTC');
        $this->grouper = new FeePlanGrouper(new \DateTimeImmutable(self::TODAY . ' 12:00:00'));
    }

    public function testGroupsPlansInStableDisplayOrder(): void
    {
        $views = $this->grouper->group([
            'pnx_10_0_0' => $this->eligibility('pnx_10_0_0', installments: 10),
            'pay_now' => $this->eligibility('pay_now', installments: 1),
            'pnx_3_0_0' => $this->eligibility('pnx_3_0_0', installments: 3),
            'deferred_15' => $this->eligibility('deferred_15', installments: 1, deferredDays: 15),
        ]);

        $this->assertSame(
            [PlanGroup::PayNow, PlanGroup::Installments, PlanGroup::Deferred, PlanGroup::Credit],
            array_map(static fn ($view) => $view->group, $views)
        );
    }

    public function testSkipsIneligiblePlans(): void
    {
        $views = $this->grouper->group([
            'pnx_3_0_0' => $this->eligibility('pnx_3_0_0', installments: 3, eligible: false),
            'pnx_4_0_0' => $this->eligibility('pnx_4_0_0', installments: 4),
        ]);

        $this->assertCount(1, $views);
        $this->assertCount(1, $views[0]->plans);
        $this->assertSame('pnx_4_0_0', $views[0]->plans[0]->planKey);
    }

    public function testEmptyInputProducesNoGroups(): void
    {
        $this->assertSame([], $this->grouper->group([]));
    }

    public function testScheduleRowsCarryAmountsAndTodayFlag(): void
    {
        $todayTs = (new \DateTimeImmutable(self::TODAY . ' 00:00:00 UTC'))->getTimestamp();
        $futureTs = $todayTs + 86400 * 30;

        $view = $this->grouper->group([
            'pnx_3_0_0' => $this->eligibility('pnx_3_0_0', installments: 3, paymentPlan: [
                ['total_amount' => 5000, 'due_date' => $todayTs],
                ['purchase_amount' => 3000, 'customer_fee' => 100, 'customer_interest' => 0, 'due_date' => $futureTs],
                ['purchase_amount' => 3000, 'customer_fee' => 100, 'customer_interest' => 50, 'due_date' => $futureTs],
            ]),
        ])[0]->plans[0];

        $this->assertTrue($view->scheduleRows[0]['isToday'], 'First installment falls due today.');
        $this->assertFalse($view->scheduleRows[1]['isToday']);
        $this->assertSame(5000, $view->scheduleRows[0]['amount'], 'total_amount wins when present.');
        $this->assertSame(3150, $view->scheduleRows[2]['amount'], 'Fallback sums purchase, fee and interest.');
        $this->assertSame(11250, $view->total);
    }

    /**
     * @param list<array<string, mixed>> $paymentPlan
     */
    private function eligibility(
        string $planKey,
        int $installments,
        int $deferredDays = 0,
        bool $eligible = true,
        array $paymentPlan = [],
    ): AlmaEligibility {
        $eligibility = $this->createMock(AlmaEligibility::class);
        $eligibility->method('getPlanKey')->willReturn($planKey);
        $eligibility->method('isEligible')->willReturn($eligible);
        $eligibility->method('getInstallmentsCount')->willReturn($installments);
        $eligibility->method('getDeferredDays')->willReturn($deferredDays);
        $eligibility->method('getDeferredMonths')->willReturn(0);
        $eligibility->method('getPaymentPlan')->willReturn($paymentPlan);
        $eligibility->method('getCustomerFee')->willReturn(0);
        $eligibility->method('getLocal')->willReturn(new FeePlanAdapter([
            'kind' => 'pnx',
            'installments_count' => $installments,
            'allowed' => true,
        ]));

        return $eligibility;
    }
}
