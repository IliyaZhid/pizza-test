<?php
require __DIR__ . '/vendor/autoload.php';

use Iliyazhid\PizzaTest\Database\Database;
use Iliyazhid\PizzaTest\Http\Request;
use Iliyazhid\PizzaTest\Http\Response;
use Iliyazhid\PizzaTest\Routing\Router;
use Iliyazhid\PizzaTest\Controllers\OrdersController;
use Iliyazhid\PizzaTest\Repositories\OrderRepository;
use Iliyazhid\PizzaTest\Middleware\AuthMiddleware;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Создаем логгер
$log = new Logger('app');
$log->pushHandler(new StreamHandler(__DIR__.'/logs/app.log', Logger::ERROR));

// Загрузка переменных окружения из .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Подключение к БД
$database = new Database([
    'host' => $_ENV['DB_HOST'],
    'port' => $_ENV['DB_PORT'],
    'dbname' => $_ENV['DB_NAME'],
    'user' => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASSWORD'],
]);

$ordersController = new OrdersController(new OrderRepository($database->getConnection(),$log));

// Настройка маршрутизации
$router = new Router();
$router->addRoute('GET', 'orders/{id}', [$ordersController, 'getOrder']);
$router->addRoute('POST', 'orders', [$ordersController, 'createOrder']);
$router->addRoute('POST', 'orders/{id}/items', [$ordersController, 'addItems']);
$router->addRoute('POST', 'orders/{id}/done', [$ordersController, 'markOrderAsDone'],
    [
        [AuthMiddleware::class, [$_ENV['AUTH_KEY']]],
    ]
);
$router->addRoute('GET', 'orders', [$ordersController, 'getOrders'],
    [
        [AuthMiddleware::class, [$_ENV['AUTH_KEY']]],
    ]
);

// Обработка запросов
$router->handle(new Request(), new Response());
