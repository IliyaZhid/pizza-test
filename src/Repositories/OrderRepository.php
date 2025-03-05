<?php

namespace Iliyazhid\PizzaTest\Repositories;

use Iliyazhid\PizzaTest\Models\Order;
use PDO;
use Psr\Log\LoggerInterface;

class OrderRepository
{
    private PDO $connection;
    private LoggerInterface $logger;


    public function __construct(PDO $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    public function getAll(?bool $done = null): array
    {
        $query = '
        SELECT o.order_id, o.done, array_agg(oi.item_id) AS items
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
    ';

        if ($done !== null) {
            $query .= ' WHERE o.done = :done';
        }

        $query .= ' GROUP BY o.order_id';

        $stmt = $this->connection->prepare($query);

        // Привязываем параметр, если он указан
        if ($done !== null) {
            $stmt->bindValue('done', $done, PDO::PARAM_BOOL);
        }

        $stmt->execute();

        $orders = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $orders[] = new Order(
                $row['order_id'],
                $this->parsePgArray($row['items']),
                $row['done']
            );
        }

        return $orders;
    }

    public function getById(string $orderId): ?Order
    {
        $stmt = $this->connection->prepare('
        SELECT o.order_id, o.done, array_agg(oi.item_id) AS items
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.order_id = :order_id
        GROUP BY o.order_id
    ');
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? new Order(
            $row['order_id'],
            $this->parsePgArray($row['items']),
            $row['done']
        ) : null;
    }

    /**
     * Преобразует строку PostgreSQL массива в массив PHP.
     *
     * @param string $pgArray Строка в формате {1,2,3}
     * @return array
     */
    private function parsePgArray(string $pgArray): array
    {
        $pgArray = trim($pgArray, '{}');
        return $pgArray ? array_map('intval', explode(',', $pgArray)) : [];
    }

    public function create(array $items): Order
    {
        $orderId = substr(uniqid(''), 0, 15);

        $this->connection->beginTransaction();

        try {
            $this->connection->prepare('INSERT INTO orders (order_id) VALUES (:order_id)')
                ->execute(['order_id' => $orderId]);
            $stmt = $this->connection->prepare('INSERT INTO order_items (order_id, item_id) VALUES (:order_id, :item_id)');
            foreach ($items as $itemId) {
                $stmt->execute(['order_id' => $orderId, 'item_id' => $itemId]);
            }
            $this->connection->commit();

            return new Order(
                $orderId,
                $items,
                false
            );
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->logger->error('Order creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function addItems(string $orderId, array $items): Order
    {
        // Начинаем транзакцию
        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->prepare('INSERT INTO order_items (order_id, item_id) VALUES (:order_id, :item_id)');
            foreach ($items as $itemId) {
                $stmt->execute(['order_id' => $orderId, 'item_id' => $itemId]);
            }
            $this->connection->commit();

            return $this->getById($orderId);
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->logger->error('Order add items failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function markAsDone(string $orderId): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->prepare('UPDATE orders SET done = TRUE WHERE order_id = :order_id')
                ->execute(['order_id' => $orderId]);
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->logger->error('Order mark failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteOrder(string $orderId): void
    {
        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->prepare('DELETE FROM orders WHERE order_id = :order_id');
            $stmt->execute(['order_id' => $orderId]);

            if ($stmt->rowCount() > 0) {
                $this->connection->commit();
            } else {
                $this->connection->rollBack();
                throw new \Exception("Заказ с ID $orderId не найден.");
            }
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->logger->error('Ошибка удаления заказа: ' . $e->getMessage());
            throw $e;
        }
    }

}
