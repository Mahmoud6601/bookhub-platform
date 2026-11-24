<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class OrderTransitionTest extends TestCase
{
    public function test_valid_order_status_transitions(): void
    {
        $order = new Order(['status' => 'pending']);
        $this->assertTrue($order->canTransitionTo('approved'));
        $this->assertTrue($order->canTransitionTo('cancelled'));
        $this->assertFalse($order->canTransitionTo('shipped'));
        $this->assertFalse($order->canTransitionTo('delivered'));
        $this->assertFalse($order->canTransitionTo('pending'));

        $order->status = 'approved';
        $this->assertTrue($order->canTransitionTo('shipped'));
        $this->assertTrue($order->canTransitionTo('cancelled'));
        $this->assertFalse($order->canTransitionTo('pending'));
        $this->assertFalse($order->canTransitionTo('delivered'));

        $order->status = 'shipped';
        $this->assertTrue($order->canTransitionTo('delivered'));
        $this->assertFalse($order->canTransitionTo('cancelled'));
        $this->assertFalse($order->canTransitionTo('approved'));
    }

    public function test_terminal_order_statuses_allow_no_transitions(): void
    {
        foreach (['delivered', 'cancelled'] as $terminal) {
            $order = new Order(['status' => $terminal]);
            foreach (Order::STATUSES as $target) {
                $this->assertFalse(
                    $order->canTransitionTo($target),
                    "Expected {$terminal} -> {$target} to be disallowed."
                );
            }
        }
    }
}
