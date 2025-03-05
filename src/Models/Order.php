<?php

namespace Iliyazhid\PizzaTest\Models;

use JsonSerializable;

class Order implements JsonSerializable
{
    private string $orderId;
    private array $items;
    private bool $done;

    public function __construct(string $orderId, array $items, bool $done = false)
    {
        $this->orderId = $orderId;
        $this->items = $items;
        $this->done = $done;
    }

    public function getOrderId(): string {
        return $this->orderId;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function jsonSerialize(): array
    {
        return [
            'order_id' => $this->orderId,
            'items' => $this->items,
            'done' => $this->done,
        ];
    }
}
