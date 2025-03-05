<?php

namespace Middleware;

use Iliyazhid\PizzaTest\Http\Request;
use Iliyazhid\PizzaTest\Http\Response;
use Iliyazhid\PizzaTest\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

class AuthMiddlewareTest extends TestCase
{
    private string $authKey;

    protected function setUp(): void
    {
       $this->authKey = $_ENV['AUTH_KEY'];
    }

    private function createMockNextHandler(bool &$nextCalled): callable
    {
        return function ($request, $response, ...$params) use (&$nextCalled) {
            $nextCalled = true;
        };
    }

    public function testSuccessfulAuth(): void
    {
        $request = new Request('GET', '/orders', ['Content-Type' => 'application/json', 'X-Auth-Key' => $this->authKey]);
        $response = new Response();

        $middleware = new AuthMiddleware($this->authKey);

        $nextCalled = false;
        $next = $this->createMockNextHandler($nextCalled);

        $middleware($request, $response, $next);

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testFailedAuth(): void
    {
        $request = new Request('GET', '/orders', ['Content-Type' => 'application/json', 'X-Auth-Key' => 'wrong-key']);
        $response = new Response();

        $middleware = new AuthMiddleware($this->authKey);

        $nextCalled = false;
        $next = $this->createMockNextHandler($nextCalled);

        ob_start();
        $middleware($request, $response, $next);
        ob_end_clean();

        $this->assertFalse($nextCalled);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testNoAuthHeader(): void
    {
        $request = new Request('GET', '/orders', ['Content-Type' => 'application/json']);
        $response = new Response();

        $middleware = new AuthMiddleware($this->authKey);

        $nextCalled = false;
        $next = $this->createMockNextHandler($nextCalled);

        ob_start();
        $middleware($request, $response, $next);
        ob_end_clean();

        $this->assertFalse($nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
    }
}
