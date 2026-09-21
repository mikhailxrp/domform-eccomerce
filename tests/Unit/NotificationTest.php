<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NotificationTest extends TestCase
{
    // ─── smsEventForStatus() ────────────────────────────────────────────

    public function testEventForNewIsAccepted(): void
    {
        $this->assertSame(SMS_EVENT_ACCEPTED, smsEventForStatus(ORDER_STATUS_NEW, FULFILLMENT_DELIVERY));
    }

    public function testEventForConfirmedIsConfirmed(): void
    {
        $this->assertSame(SMS_EVENT_CONFIRMED, smsEventForStatus(ORDER_STATUS_CONFIRMED, FULFILLMENT_DELIVERY));
    }

    public function testEventForInProductionIsStatusChanged(): void
    {
        $this->assertSame(SMS_EVENT_STATUS_CHANGED, smsEventForStatus(ORDER_STATUS_IN_PRODUCTION, FULFILLMENT_DELIVERY));
    }

    public function testEventForShippingIsStatusChanged(): void
    {
        $this->assertSame(SMS_EVENT_STATUS_CHANGED, smsEventForStatus(ORDER_STATUS_SHIPPING, FULFILLMENT_DELIVERY));
    }

    public function testEventForReadyForShipmentIsReadyRegardlessOfFulfillment(): void
    {
        $this->assertSame(SMS_EVENT_READY, smsEventForStatus(ORDER_STATUS_READY_FOR_SHIPMENT, FULFILLMENT_DELIVERY));
        $this->assertSame(SMS_EVENT_READY, smsEventForStatus(ORDER_STATUS_READY_FOR_SHIPMENT, FULFILLMENT_PICKUP));
    }

    public function testEventForCancelledIsCancelled(): void
    {
        $this->assertSame(SMS_EVENT_CANCELLED, smsEventForStatus(ORDER_STATUS_CANCELLED, FULFILLMENT_DELIVERY));
    }

    public function testEventForDeliveredIsNull(): void
    {
        $this->assertNull(smsEventForStatus(ORDER_STATUS_DELIVERED, FULFILLMENT_DELIVERY));
    }

    // ─── smsMessageForEvent() ───────────────────────────────────────────

    public function testMessageContainsOrderId(): void
    {
        $message = smsMessageForEvent(SMS_EVENT_ACCEPTED, 42, FULFILLMENT_DELIVERY);

        $this->assertStringContainsString('42', $message);
    }

    public function testReadyMessageDiffersByFulfillment(): void
    {
        $delivery = smsMessageForEvent(SMS_EVENT_READY, 1, FULFILLMENT_DELIVERY);
        $pickup   = smsMessageForEvent(SMS_EVENT_READY, 1, FULFILLMENT_PICKUP);

        $this->assertStringContainsString('доставке', $delivery);
        $this->assertStringContainsString('выдаче', $pickup);
    }

    // ─── orderNotificationPhone() ───────────────────────────────────────

    public function testPhoneFromUserWhenUserHasPhone(): void
    {
        $order = ['guest_phone' => null];
        $user  = ['phone' => '+79001234567'];

        $this->assertSame('+79001234567', orderNotificationPhone($order, $user));
    }

    public function testPhoneFromGuestWhenNoUser(): void
    {
        $order = ['guest_phone' => '+79007654321'];

        $this->assertSame('+79007654321', orderNotificationPhone($order, null));
    }

    public function testPhoneIsNullWhenNeitherUserNorGuestHasIt(): void
    {
        $order = ['guest_phone' => null];
        $user  = ['phone' => null];

        $this->assertNull(orderNotificationPhone($order, $user));
    }
}
