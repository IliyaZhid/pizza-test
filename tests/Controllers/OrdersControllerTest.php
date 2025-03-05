<?php

namespace Controllers;

use Iliyazhid\PizzaTest\Database\Database;
use Iliyazhid\PizzaTest\Http\Request;
use Iliyazhid\PizzaTest\Http\Response;
use Iliyazhid\PizzaTest\Controllers\OrdersController;
use Iliyazhid\PizzaTest\Models\Order;
use Iliyazhid\PizzaTest\Repositories\OrderRepository;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class OrdersControllerTest extends TestCase
{
    private OrderRepository $repository;
    private OrdersController $controller;
    private array $affectedOrdersIds = [];

    protected function setUp(): void
    {
        $database = new Database([
            'host' => $_ENV['DB_HOST'],
            'port' => $_ENV['DB_PORT'],
            'dbname' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
        ]);
        $log = new Logger('app');
        $log->pushHandler(new StreamHandler(__DIR__ . '/logs/app.log', Logger::ERROR));
        $this->repository = new OrderRepository($database->getConnection(), $log);
        $this->controller = new OrdersController($this->repository);
    }

    protected function tearDown(): void
    {
        foreach ($this->affectedOrdersIds as $affectedOrderId) {
            $this->repository->deleteOrder($affectedOrderId);
        }
    }

    private function createOrder(array $items): Order
    {
        try {
            $order = $this->repository->create($items);
            $this->affectedOrdersIds[] = $order->getOrderId();
            return $order;
        } catch (\Exception $e) {
            $this->fail("Ошибка создания заказа: {$e->getMessage()}");
        }
    }

    private function getRandItems(int $itemsCount = 5): array
    {
        $randItems = [];
        for ($i = 0; $i < $itemsCount; $i++) {
            $randItems[] = rand(1, 5000);
        }
        return $randItems;
    }

    public function testCreateOrder(): void
    {
        $items = $this->getRandItems();
        $request = new Request('POST', '/orders', ['Content-Type' => 'application/json'], ['items' => $items]);
        $response = new Response();

        ob_start();
        $this->controller->createOrder($request, $response);
        ob_end_clean();
        $responseData = json_decode($response->getBody(), true);

        if ($responseData['order_id'] && mb_strlen($responseData['order_id']) > 0) {
            $this->affectedOrdersIds[] = $responseData['order_id'];
        }

        $this->assertArrayHasKey('order_id', $responseData);
        $this->assertFalse($responseData['done']);
        $this->assertEquals($items, $responseData['items']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetOrder(): void
    {
        $items = $this->getRandItems();
        $order = $this->createOrder($items);
        $request = new Request('GET', "/orders/{$order->getOrderId()}", ['Content-Type' => 'application/json']);
        $response = new Response();

        ob_start();
        $this->controller->getOrder($request, $response, $order->getOrderId());
        ob_end_clean();

        $this->assertJsonStringEqualsJsonString(
            json_encode($order->jsonSerialize()),
            $response->getBody()
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAddItems(): void
    {
        $items = $this->getRandItems();
        $order = $this->createOrder($items);
        $request = new Request('POST', "/orders/{$order->getOrderId()}", ['Content-Type' => 'application/json'], [...$items]);
        $response = new Response();

        ob_start();
        $this->controller->addItems($request, $response, $order->getOrderId());
        ob_end_clean();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMarkOrderAsDone(): void
    {
        $items = $this->getRandItems();
        $order = $this->createOrder($items);
        $request = new Request('POST', "/orders/{$order->getOrderId()}/done", ['Content-Type' => 'application/json']);
        $response = new Response();

        ob_start();
        $this->controller->markOrderAsDone($request, $response, $order->getOrderId());
        ob_end_clean();

        $order = $this->repository->getById($order->getOrderId());
        $this->assertTrue($order->isDone());
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetOrders(): void
    {
        $firstItems = $this->getRandItems();
        $secondItems = $this->getRandItems();
        $firstOrder = $this->createOrder($firstItems);
        $secondOrder = $this->createOrder($secondItems);

        $request = new Request('GET', "/orders", ['Content-Type' => 'application/json']);
        $response = new Response();

        ob_start();
        $this->controller->getOrders($request, $response);
        ob_end_clean();

        $responseData = json_decode($response->getBody(), true);
        $this->assertContains($firstOrder->jsonSerialize(), $responseData);
        $this->assertContains($secondOrder->jsonSerialize(), $responseData);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetOrdersWithDoneParam(): void
    {
        $this->assertOrdersFilteredByDoneParam(true, function ($order) {
            if (!$order['done']) {
                $this->fail("wrong result ?done=true");
            }
        });

        $this->assertOrdersFilteredByDoneParam(false, function ($order) {
            if ($order['done']) {
                $this->fail("wrong result ?done=false");
            }
        });
    }

    private function assertOrdersFilteredByDoneParam(bool $done, callable $assertCallback): void
    {
        $request = new Request('GET', "/orders", ['Content-Type' => 'application/json'], [], ['done' => $done]);
        $response = new Response();

        ob_start();
        $this->controller->getOrders($request, $response);
        ob_end_clean();

        $this->assertEquals(200, $response->getStatusCode());

        $responseData = json_decode($response->getBody(), true);

        foreach ($responseData as $order) {
            $assertCallback($order);
        }
    }
}
