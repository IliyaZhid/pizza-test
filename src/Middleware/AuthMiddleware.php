<?php

namespace Iliyazhid\PizzaTest\Middleware;

use Iliyazhid\PizzaTest\Http\Request;
use Iliyazhid\PizzaTest\Http\Response;

class AuthMiddleware
{
    private string $authKey;

    public function __construct(string $authKey)
    {
        $this->authKey = $authKey;
    }

    public function __invoke(Request $request, Response $response, callable $next,  ...$params): void
    {
        $authKey = $request->getHeader('X-Auth-Key');

        if ($authKey !== $this->authKey) {
            $response->json(['error' => 'Неверный ключ авторизации'], 401)->send();
            return;
        }

        $next($request, $response,  ...$params);
    }
}
