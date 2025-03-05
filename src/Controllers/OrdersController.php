<?php

namespace Iliyazhid\PizzaTest\Controllers;

use Iliyazhid\PizzaTest\Http\Response;
use Iliyazhid\PizzaTest\Http\Request;
use Iliyazhid\PizzaTest\Repositories\OrderRepository;

class OrdersController extends Controller
{
    private OrderRepository $repository;

    public function __construct(OrderRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getOrder(Request $request, Response $response, string $orderId): void
    {
        $order = $this->repository->getById($orderId);
        $order ? $this->jsonResponse($response, $order) : $this->notFound($response);
    }

    public function getOrders(Request $request, Response $response): void
    {
        $done = $request->getQueryParam('done');

        // Преобразуем параметр в булево значение, если он указан
        if ($done !== null) {
            $done = filter_var($done, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($done === null) {
                $this->badRequest($response, 'Некорректное значение параметра done');
                return;
            }
        }

        $orders = $this->repository->getAll($done);
        $this->jsonResponse($response, $orders);
    }

    public function createOrder(Request $request, Response $response): void
    {
        $input = $request->getBody();

        if (!isset($input['items']) || !is_array($input['items'])) {
            $this->badRequest($response, 'Поле "items" должно быть массивом');
            return;
        }

        if(count($input['items']) == 0) {
            $this->badRequest($response, 'Заказ не может быть пустым');
            return;
        }

        foreach ($input['items'] as $itemId) {
            if (!is_int($itemId) || $itemId < 1 || $itemId > 5000) {
                $this->badRequest($response, 'Некорректный item_id');
                return;
            }
        }

        try {
            $newOrder = $this->repository->create($input['items']);
            $this->jsonResponse($response, $newOrder);
        } catch (\Exception $e) {
            $this->internalServerError($response, $e->getMessage());
        }

    }

    public function addItems(Request $request, Response $response, string $orderId): void
    {
        $input = $request->getBody();

        if (!is_array($input)) {
            $this->badRequest($response, 'Тело запроса должно быть массивом');
            return;
        }

        if(count($input) == 0) {
            $this->badRequest($response, 'Массив товаров не может быть пустым');
            return;
        }

        foreach ($input as $itemId) {
            if (!is_int($itemId) || $itemId < 1 || $itemId > 5000) {
                $this->badRequest($response, 'Некорректный item_id');
                return;
            }
        }

        $order = $this->repository->getById($orderId);
        if ($order == null) {
            $this->notFound($response);
            return;
        }

        if($order->isDone()) {
            $this->badRequest($response, 'Нельзя редактировать выполненный заказ');
            return;
        }

        try {
            $this->repository->addItems($orderId, $input);
            $this->jsonResponse($response, null);
        } catch (\Exception $e) {
            $this->internalServerError($response, $e->getMessage());
        }
    }

    public function markOrderAsDone(Request $request, Response $response, string $orderId): void
    {
        $order = $this->repository->getById($orderId);

        if ($order == null) {
            $this->notFound($response);
            return;
        }

        if($order->isDone()) {
            $this->badRequest($response, 'Нельзя редактировать выполненный заказ');
            return;
        }

        try {
            $this->repository->markAsDone($orderId);
            $this->jsonResponse($response, null);
        } catch (\Exception $e) {
            $this->internalServerError($response, $e->getMessage());
        }
    }
}
