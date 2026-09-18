<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    // ─── allowedOrderTransitions()/canTransitionOrder() — обычный Вариант ──

    public function testNewToConfirmedAllowed(): void
    {
        $this->assertTrue(canTransitionOrder(ORDER_STATUS_NEW, ORDER_STATUS_CONFIRMED, false, FULFILLMENT_DELIVERY));
    }

    public function testNewToCancelledAllowed(): void
    {
        $this->assertTrue(canTransitionOrder(ORDER_STATUS_NEW, ORDER_STATUS_CANCELLED, false, FULFILLMENT_DELIVERY));
    }

    public function testNewToShippingForbidden(): void
    {
        $this->assertFalse(canTransitionOrder(ORDER_STATUS_NEW, ORDER_STATUS_SHIPPING, false, FULFILLMENT_DELIVERY));
    }

    public function testConfirmedRegularVariantAllowsOnlyInProductionAndCancelled(): void
    {
        $allowed = allowedOrderTransitions(ORDER_STATUS_CONFIRMED, false, FULFILLMENT_DELIVERY);

        sort($allowed);
        $this->assertSame([ORDER_STATUS_CANCELLED, ORDER_STATUS_IN_PRODUCTION], $allowed);
    }

    public function testConfirmedRegularVariantCannotSkipToReadyForShipment(): void
    {
        $this->assertFalse(canTransitionOrder(ORDER_STATUS_CONFIRMED, ORDER_STATUS_READY_FOR_SHIPMENT, false, FULFILLMENT_DELIVERY));
    }

    // ─── Выставочный образец — пропускает «В производстве» ─────────────────

    public function testConfirmedShowroomSampleAllowsReadyForShipmentAndCancelled(): void
    {
        $allowed = allowedOrderTransitions(ORDER_STATUS_CONFIRMED, true, FULFILLMENT_DELIVERY);

        sort($allowed);
        $this->assertSame([ORDER_STATUS_CANCELLED, ORDER_STATUS_READY_FOR_SHIPMENT], $allowed);
    }

    public function testConfirmedShowroomSampleCannotGoToInProduction(): void
    {
        $this->assertFalse(canTransitionOrder(ORDER_STATUS_CONFIRMED, ORDER_STATUS_IN_PRODUCTION, true, FULFILLMENT_DELIVERY));
    }

    public function testInProductionAllowsReadyForShipmentAndCancelled(): void
    {
        $allowed = allowedOrderTransitions(ORDER_STATUS_IN_PRODUCTION, false, FULFILLMENT_DELIVERY);

        sort($allowed);
        $this->assertSame([ORDER_STATUS_CANCELLED, ORDER_STATUS_READY_FOR_SHIPMENT], $allowed);
    }

    // ─── Ветвление по способу получения из «Готов к отгрузке» ──────────────

    public function testReadyForShipmentDeliveryGoesToShipping(): void
    {
        $this->assertSame(
            [ORDER_STATUS_SHIPPING],
            allowedOrderTransitions(ORDER_STATUS_READY_FOR_SHIPMENT, false, FULFILLMENT_DELIVERY)
        );
    }

    public function testReadyForShipmentPickupGoesToDelivered(): void
    {
        $this->assertSame(
            [ORDER_STATUS_DELIVERED],
            allowedOrderTransitions(ORDER_STATUS_READY_FOR_SHIPMENT, false, FULFILLMENT_PICKUP)
        );
    }

    public function testShippingGoesToDelivered(): void
    {
        $this->assertSame(
            [ORDER_STATUS_DELIVERED],
            allowedOrderTransitions(ORDER_STATUS_SHIPPING, false, FULFILLMENT_DELIVERY)
        );
    }

    // ─── Отмена запрещена после отгрузки, терминальные статусы без переходов

    public function testCancelForbiddenFromReadyForShipment(): void
    {
        $this->assertFalse(canTransitionOrder(ORDER_STATUS_READY_FOR_SHIPMENT, ORDER_STATUS_CANCELLED, false, FULFILLMENT_DELIVERY));
    }

    public function testCancelForbiddenFromShipping(): void
    {
        $this->assertFalse(canTransitionOrder(ORDER_STATUS_SHIPPING, ORDER_STATUS_CANCELLED, false, FULFILLMENT_DELIVERY));
    }

    public function testCancelForbiddenFromDelivered(): void
    {
        $this->assertFalse(canTransitionOrder(ORDER_STATUS_DELIVERED, ORDER_STATUS_CANCELLED, false, FULFILLMENT_DELIVERY));
    }

    public function testCancelForbiddenFromCancelled(): void
    {
        $this->assertFalse(canTransitionOrder(ORDER_STATUS_CANCELLED, ORDER_STATUS_CANCELLED, false, FULFILLMENT_DELIVERY));
    }

    public function testTerminalStatusesHaveNoTransitions(): void
    {
        $this->assertSame([], allowedOrderTransitions(ORDER_STATUS_DELIVERED, false, FULFILLMENT_DELIVERY));
        $this->assertSame([], allowedOrderTransitions(ORDER_STATUS_CANCELLED, false, FULFILLMENT_DELIVERY));
    }

    public function testUnknownStatusHasNoTransitions(): void
    {
        $this->assertSame([], allowedOrderTransitions('bogus', false, FULFILLMENT_DELIVERY));
    }

    // ─── canCancelOrder() ────────────────────────────────────────────────

    public function testCanCancelOrderTrueForNewConfirmedInProduction(): void
    {
        $this->assertTrue(canCancelOrder(ORDER_STATUS_NEW));
        $this->assertTrue(canCancelOrder(ORDER_STATUS_CONFIRMED));
        $this->assertTrue(canCancelOrder(ORDER_STATUS_IN_PRODUCTION));
    }

    public function testCanCancelOrderFalseAfterReadyForShipment(): void
    {
        $this->assertFalse(canCancelOrder(ORDER_STATUS_READY_FOR_SHIPMENT));
        $this->assertFalse(canCancelOrder(ORDER_STATUS_SHIPPING));
        $this->assertFalse(canCancelOrder(ORDER_STATUS_DELIVERED));
        $this->assertFalse(canCancelOrder(ORDER_STATUS_CANCELLED));
    }

    // ─── orderStatusLabel() ──────────────────────────────────────────────

    public function testOrderStatusLabelReturnsRussianText(): void
    {
        $this->assertSame('Новый', orderStatusLabel(ORDER_STATUS_NEW));
        $this->assertSame('Доставлен/Собран', orderStatusLabel(ORDER_STATUS_DELIVERED));
    }

    public function testOrderStatusLabelFallsBackToRawValueForUnknownStatus(): void
    {
        $this->assertSame('bogus', orderStatusLabel('bogus'));
    }

    // ─── orderStatusBadgeClass() ─────────────────────────────────────────

    public function testOrderStatusBadgeClassForEachOfSevenStatuses(): void
    {
        $this->assertSame('bg-secondary', orderStatusBadgeClass(ORDER_STATUS_NEW));
        $this->assertSame('bg-info', orderStatusBadgeClass(ORDER_STATUS_CONFIRMED));
        $this->assertSame('bg-primary', orderStatusBadgeClass(ORDER_STATUS_IN_PRODUCTION));
        $this->assertSame('bg-warning text-dark', orderStatusBadgeClass(ORDER_STATUS_READY_FOR_SHIPMENT));
        $this->assertSame('bg-warning', orderStatusBadgeClass(ORDER_STATUS_SHIPPING));
        $this->assertSame('bg-success', orderStatusBadgeClass(ORDER_STATUS_DELIVERED));
        $this->assertSame('bg-danger', orderStatusBadgeClass(ORDER_STATUS_CANCELLED));
    }

    public function testOrderStatusBadgeClassFallsBackForUnknownStatus(): void
    {
        $this->assertSame('bg-light text-dark', orderStatusBadgeClass('bogus'));
    }
}
